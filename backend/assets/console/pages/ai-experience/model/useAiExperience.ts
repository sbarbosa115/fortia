import {
  type ChatDraft,
  type ChatItem,
  type ChatMessage,
  type ChatPendingWrite,
  type ChatTurnResult,
  MAX_CHAT_MESSAGE_LENGTH,
  MAX_CHAT_MESSAGES,
  sendChatTurn,
} from '@console/entities/chat';
import {USAGE_QUERY_KEY} from '@console/entities/plan-usage';
import {
  fetchQuestionnaire,
  questionnairePublicUrl,
  questionnaireQueryKey,
} from '@console/entities/questionnaire';
import {isApiError} from '@shared/api';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {useCallback, useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';
import {
  answeredScore,
  draftInProgress,
  type PreviewAnswers,
  type PreviewTab,
  previewPartAfter,
  previewScreens,
  previewTabOf,
  previewTabsOf,
  screenIndexFor,
  screenIndexForTab,
} from './preview';

export type ChatStatus = 'idle' | 'sending' | 'error' | 'created';
export type PreviewDevice = 'mobile' | 'desktop';

/** Plan refusals: retrying only refuses again, so they have no Retry and show in amber (PRD §10.21). */
const PLAN_CODES = [
  'PLAN_LIMIT_REACHED',
  'FEATURE_NOT_IN_PLAN',
  'NO_PLAN',
  'PLAN_INACTIVE',
  'PLAN_NOT_FOUND',
];

/** Below this width the chat needs the room: the preview starts hidden and opens as a drawer. */
export const PREVIEW_MEDIA_QUERY = '(min-width: 1100px)';

/** The composer shows its counter only this close to the limit. */
const COUNTER_THRESHOLD = 0.9;

function previewFitsByDefault(): boolean {
  return typeof window === 'undefined' ||
    typeof window.matchMedia !== 'function'
    ? true
    : window.matchMedia(PREVIEW_MEDIA_QUERY).matches;
}

function now(): string {
  return new Date().toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
  });
}

/**
 * AI Experience (PRD §7.19, §10.4): a fullscreen conversation that builds the questionnaire basics → questions →
 * ending over a live draft, previewed on the right as the respondent would walk and answer it; nothing is created
 * until the author confirms. The client keeps the messages, the draft and the pending writes and sends them with
 * every turn; each turn is a `chat` job.
 */
export function useAiExperience() {
  const {t, i18n} = useTranslation('pages.ai-experience');
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [messages, setMessages] = useState<ChatMessage[]>([]);
  // Display timestamps, parallel to `messages` by index; never sent.
  const [times, setTimes] = useState<string[]>([]);
  const [greetingTime, setGreetingTime] = useState(now);
  const [input, setInput] = useState('');
  const [status, setStatus] = useState<ChatStatus>('idle');
  const [failure, setFailure] = useState<unknown>(null);
  const [draft, setDraft] = useState<ChatDraft | null>(null);
  const [pendingWrites, setPendingWrites] = useState<ChatPendingWrite[]>([]);
  const [quickReplies, setQuickReplies] = useState<string[]>([]);
  const [createdId, setCreatedId] = useState<string | null>(null);
  const [previewOpen, setPreviewOpen] = useState(previewFitsByDefault);
  const [device, setDevice] = useState<PreviewDevice>('mobile');
  const [screenIndex, setScreenIndex] = useState(0);
  const [tierIndex, setTierIndex] = useState<number | null>(null);
  const [answers, setAnswers] = useState<PreviewAnswers>({});

  // The item the last user turn points at (a clicked name); a retry re-sends it with the turn.
  const turnItem = useRef<ChatItem | null>(null);
  // Bumped by a new chat, so the reply of a turn sent before it is dropped.
  const conversation = useRef(0);
  const abort = useRef<AbortController | null>(null);

  useEffect(() => () => abort.current?.abort(), []);

  const hasPreview = draftInProgress(draft);

  // A new draft: the first one of a questionnaire opens the preview as the page would, whatever the toggle was left
  // at; every one shows the part that changed.
  const adoptDraft = useCallback(
    (next: ChatDraft) => {
      if (!draftInProgress(draft) && draftInProgress(next)) {
        setPreviewOpen(previewFitsByDefault());
      }
      const screens = previewScreens(next);
      const part = previewPartAfter(draft, next);
      setScreenIndex((index) =>
        part
          ? screenIndexFor(part, screens)
          : Math.min(index, Math.max(screens.length - 1, 0)),
      );
      setDraft(next);
    },
    [draft],
  );

  const applyResult = useCallback(
    (result: ChatTurnResult, history: ChatMessage[]) => {
      if (result.draft) {
        adoptDraft(result.draft);
      }
      setPendingWrites(result.pending_writes);
      if (result.actions.length > 0) {
        // The chat changed the account: whatever the other screens cached may be stale.
        void queryClient.invalidateQueries();
        const language = result.actions.find(
          (action) => action.language,
        )?.language;
        if (language) {
          void i18n.changeLanguage(language);
        }
      } else {
        void queryClient.invalidateQueries({queryKey: USAGE_QUERY_KEY});
      }
      setQuickReplies(result.quick_replies);
      setMessages([...history, {role: 'assistant', content: result.message}]);
      setTimes((previous) => [...previous, now()]);
      if (
        result.type === 'chat-questionnaire-created' &&
        result.questionnaire_id
      ) {
        setCreatedId(result.questionnaire_id);
        setStatus('created');
      } else {
        setStatus('idle');
      }
    },
    [queryClient, i18n, adoptDraft],
  );

  const send = useCallback(
    async (history: ChatMessage[]) => {
      const turn = conversation.current;
      abort.current?.abort();
      const controller = new AbortController();
      abort.current = controller;
      setStatus('sending');
      setFailure(null);
      try {
        const result = await sendChatTurn(
          {
            messages: history,
            mode: 'create',
            draft,
            item: turnItem.current,
            pending_writes: pendingWrites,
          },
          controller.signal,
        );
        if (turn !== conversation.current) {
          return;
        }
        applyResult(result, history);
      } catch (error) {
        if (turn !== conversation.current || controller.signal.aborted) {
          return;
        }
        setFailure(error);
        setStatus('error');
      }
    },
    [draft, pendingWrites, applyResult],
  );

  const limitReached = messages.length >= MAX_CHAT_MESSAGES - 1;
  const busy = status === 'sending' || status === 'created';
  const canSend = !busy && !limitReached && input.trim().length > 0;

  const sendText = (text: string, item: ChatItem | null = null) => {
    if (busy || limitReached || !text.trim()) {
      return;
    }
    turnItem.current = item;
    const history: ChatMessage[] = [
      ...messages,
      {role: 'user', content: text.trim()},
    ];
    setQuickReplies([]);
    setMessages(history);
    setTimes((previous) => [...previous, now()]);
    setInput('');
    void send(history);
  };

  const newChat = () => {
    conversation.current += 1;
    abort.current?.abort();
    turnItem.current = null;
    setMessages([]);
    setTimes([]);
    setGreetingTime(now());
    setInput('');
    setStatus('idle');
    setFailure(null);
    setDraft(null);
    setScreenIndex(0);
    setAnswers({});
    setTierIndex(null);
    setPendingWrites([]);
    setQuickReplies([]);
    setCreatedId(null);
  };

  const created = useQuery({
    queryKey: questionnaireQueryKey(createdId ?? ''),
    queryFn: () => fetchQuestionnaire(createdId ?? ''),
    enabled: createdId !== null,
  });

  const screens = previewScreens(draft);
  const lastScreen = Math.max(screens.length - 1, 0);
  const screen = screens[screenIndex] ?? null;
  const tiers = draft?.type === 'diagnostic' ? draft.ending.tiers : [];
  const score = draft ? answeredScore(draft, answers) : null;
  // Before any answer, or once a level is picked in "View as", the result shows that level; else the score's share.
  const scoredTier =
    score && tiers.length > 0 && tierIndex === null
      ? Math.min(
          tiers.length - 1,
          Math.floor(
            (score.max > 0 ? score.score / score.max : 0) * tiers.length,
          ),
        )
      : null;
  const activeTier = tiers.length > 0 ? (scoredTier ?? tierIndex ?? 0) : null;

  const answer = (index: number, value: string) => {
    const multi = draft?.questions[index]?.type === 'checkbox';
    setAnswers((current) => {
      const chosen = current[index] ?? [];
      const next = multi
        ? chosen.includes(value)
          ? chosen.filter((item) => item !== value)
          : [...chosen, value]
        : [value];
      return {...current, [index]: next};
    });
    // Answering hands the result back to the answers, off whatever level "View as" showed.
    setTierIndex(null);
  };

  const plan =
    isApiError(failure) &&
    (failure.isPlanLimit || PLAN_CODES.includes(failure.code));

  return {
    // Conversation
    messages,
    times,
    greetingTime,
    quickReplies,
    status,
    failure,
    planFailure: plan,
    canRetry: status === 'error' && !plan,
    input,
    setInput,
    maxLength: MAX_CHAT_MESSAGE_LENGTH,
    showCounter: input.length >= MAX_CHAT_MESSAGE_LENGTH * COUNTER_THRESHOLD,
    canSend,
    disabled: busy || limitReached,
    limitReached: limitReached && status !== 'created',
    handleSend: () => {
      if (canSend) {
        sendText(input);
      }
    },
    pickReply: (reply: string) => sendText(reply),
    askItem: (item: ChatItem, name: string) =>
      sendText(t(`askDetails.${item.kind}`, {name}), item),
    retry: () => void send(messages),
    newChat,
    back: () => navigate('/questionnaires/new'),
    // What was created
    created: createdId !== null,
    createdTitle: draft?.title ?? '',
    viewUrl: created.data ? questionnairePublicUrl(created.data) : '',
    editCreated: () => {
      if (createdId) {
        navigate(`/questionnaires/${createdId}/edit`);
      }
    },
    // Preview
    draft,
    hasPreview,
    previewOpen: hasPreview && previewOpen,
    togglePreview: () => setPreviewOpen((open) => !open),
    closePreview: useCallback(() => setPreviewOpen(false), []),
    device,
    setDevice,
    screen,
    canGoBack: screenIndex > 0,
    previousScreen: () => setScreenIndex((index) => Math.max(index - 1, 0)),
    nextScreen: () =>
      setScreenIndex((index) => Math.min(index + 1, lastScreen)),
    restartPreview: () => setScreenIndex(0),
    previewTabs: previewTabsOf(screens).map((id) => ({
      id,
      label: t(`preview.tab.${id}`),
    })),
    activeTab: previewTabOf(screen) ?? undefined,
    goToTab: (id: PreviewTab) => setScreenIndex(screenIndexForTab(id, screens)),
    answers,
    answer,
    tiers,
    activeTier,
    pickTier: setTierIndex,
    showTierChips: tiers.length > 0 && screen?.kind === 'final',
    score,
  };
}

export type AiExperience = ReturnType<typeof useAiExperience>;
