import type {ChatDraft, ChatTurnResult} from '@console/entities/chat';
import {
  fetchQuestionnaire,
  questionnairePublicUrl,
  questionnaireQueryKey,
} from '@console/entities/questionnaire';
import {useChat} from '@console/widgets/chat-panel';
import {useQuery} from '@tanstack/react-query';
import {useCallback, useState} from 'react';
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

export type PreviewDevice = 'mobile' | 'desktop';

/** Below this width the chat needs the room: the preview starts hidden and opens as a drawer. */
export const PREVIEW_MEDIA_QUERY = '(min-width: 1100px)';

function previewFitsByDefault(): boolean {
  return typeof window === 'undefined' ||
    typeof window.matchMedia !== 'function'
    ? true
    : window.matchMedia(PREVIEW_MEDIA_QUERY).matches;
}

/**
 * AI Experience (PRD §7.19, §10.4): a fullscreen conversation that builds the questionnaire basics → questions →
 * ending over a live draft, previewed on the right as the respondent would walk and answer it; nothing is created
 * until the author confirms. The conversation is the chat widget, the same as in /projects/new (create mode here);
 * this page adds the preview and what was created.
 */
export function useAiExperience() {
  const {t} = useTranslation('pages.ai-experience');
  const navigate = useNavigate();

  const [createdId, setCreatedId] = useState<string | null>(null);
  const [previewOpen, setPreviewOpen] = useState(previewFitsByDefault);
  const [device, setDevice] = useState<PreviewDevice>('mobile');
  const [screenIndex, setScreenIndex] = useState(0);
  const [tierIndex, setTierIndex] = useState<number | null>(null);
  const [answers, setAnswers] = useState<PreviewAnswers>({});

  // A new draft: the first one of a questionnaire opens the preview as the page would, whatever the toggle was left
  // at; every one shows the part that changed.
  const onResult = useCallback(
    (result: ChatTurnResult, previous: ChatDraft | null) => {
      const next = result.draft ?? null;
      if (next) {
        if (!draftInProgress(previous) && draftInProgress(next)) {
          setPreviewOpen(previewFitsByDefault());
        }
        const screens = previewScreens(next);
        const part = previewPartAfter(previous, next);
        setScreenIndex((index) =>
          part
            ? screenIndexFor(part, screens)
            : Math.min(index, Math.max(screens.length - 1, 0)),
        );
      }
      if (
        result.type === 'chat-questionnaire-created' &&
        result.questionnaire_id
      ) {
        setCreatedId(result.questionnaire_id);
      }
    },
    [],
  );
  const chat = useChat({
    mode: 'create',
    onResult,
    closed: createdId !== null,
  });
  const {draft} = chat;
  const hasPreview = draftInProgress(draft);

  const newChat = () => {
    chat.reset();
    setScreenIndex(0);
    setAnswers({});
    setTierIndex(null);
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

  return {
    /** The conversation, the same chat as /projects/new. */
    chat,
    greeting: t('greeting'),
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
