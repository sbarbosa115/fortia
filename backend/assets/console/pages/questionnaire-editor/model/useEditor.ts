import {
  QUESTIONNAIRES_QUERY_KEY,
  createQuestionnaire,
  fetchQuestionnaire,
  questionnaireQueryKey,
  updateQuestionnaire,
  uploadPromptText,
} from '@console/entities/questionnaire';
import {useViewer} from '@console/entities/viewer';
import {ApiError} from '@shared/api';
import {useToast} from '@shared/ui';
import {useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  duplicateQuestion,
  groupByCategory,
  moveQuestion,
  newKey,
  newQuestion,
  withType,
} from './draft';
import {editRouteKind} from './decode';
import {encodeFlow} from './encode';
import {maxScores, seedTiers} from './scoring';
import {
  type Draft,
  type DraftQuestion,
  type FieldType,
  MAX_PROMPTS,
} from './types';
import {type Issue, type Step, validateDraft, validateStep} from './validate';

export type EditorMode = 'create' | 'edit';

/** Below this width the editor needs the room: the preview starts closed and opens as a drawer. */
export const PREVIEW_MEDIA_QUERY = '(min-width: 1100px)';

function previewFits(): boolean {
  return typeof window === 'undefined' ||
    typeof window.matchMedia !== 'function'
    ? false
    : window.matchMedia(PREVIEW_MEDIA_QUERY).matches;
}

export type SavedResult = {
  questionnaireId: string;
  slug: string | null;
};

/**
 * The creation container's state (PRD §10.5): the draft, the current step, the preview, the create confirmation,
 * saving (no autosave: only the main action saves), the success screen and the Locked state a 409 leads to.
 */
export function useEditor({
  initial,
  mode,
  questionnaireId,
}: {
  initial: Draft;
  mode: EditorMode;
  questionnaireId: string | null;
}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const viewer = useViewer();
  const toast = useToast();
  const queryClient = useQueryClient();
  const [draft, setDraft] = useState<Draft>(initial);
  const [step, setStep] = useState<Step>(1);
  // The live preview starts open when it fits beside the editor (as in the admin console).
  const [previewOpen, setPreviewOpen] = useState(previewFits);
  /** The screen the preview shows where a step has several: cover / disclaimer, contact / final. */
  const [previewTab, setPreviewTab] = useState('cover');
  /** The question the Questions step edits (one at a time, picked in the outline). */
  const [selectedKey, setSelectedKey] = useState<string | null>(
    initial.questions[0]?.key ?? null,
  );
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState<SavedResult | null>(null);
  const [locked, setLocked] = useState(false);
  const [slugInUse, setSlugInUse] = useState<string | null>(null);
  /** The question a refused save pointed at (single-page editor): its card is outlined until it is edited. */
  const [flagged, setFlagged] = useState<string | null>(null);
  const [expanded, setExpanded] = useState<Set<string>>(
    () =>
      new Set(
        mode === 'create'
          ? initial.questions.map((q) => q.key)
          : initial.questions.slice(0, 1).map((q) => q.key),
      ),
  );

  const issuesOf = (s: Step): Issue[] => validateStep(draft, s);
  const allIssues = validateDraft(draft);
  const message = (issue: Issue | undefined) =>
    issue ? t(issue.key, issue.params) : null;
  /** A step is reachable when every step before it is valid; when editing, all are. */
  const canReach = (target: Step) =>
    mode === 'edit' ||
    ([1, 2, 3] as Step[])
      .filter((s) => s < target)
      .every((s) => issuesOf(s).length === 0);

  const update = (patch: Partial<Draft>) => {
    setDraft((current) => ({...current, ...patch}));
    if ('slug' in patch) {
      setSlugInUse(null);
    }
  };

  const selectedIndex = Math.max(
    0,
    draft.questions.findIndex((q) => q.key === selectedKey),
  );
  const selected = draft.questions[selectedIndex] ?? null;
  /** The question the Questions step's first problem is in. */
  const problemKey =
    issuesOf(2).find((issue) =>
      draft.questions.some((q) => q.key === issue.field),
    )?.field ?? null;

  /** Swaps a question with its neighbour inside its category (the outline's order). */
  const neighbour = (key: string, delta: -1 | 1): number | null => {
    const index = draft.questions.findIndex((q) => q.key === key);
    const other = draft.questions[index + delta];
    const question = draft.questions[index];
    return question &&
      other &&
      other.category.trim() === question.category.trim()
      ? index + delta
      : null;
  };

  const setQuestions = (fn: (questions: DraftQuestion[]) => DraftQuestion[]) =>
    setDraft((current) => ({...current, questions: fn(current.questions)}));

  const updateQuestion = (key: string, patch: Partial<DraftQuestion>) => {
    setFlagged((current) => (current === key ? null : current));
    setQuestions((questions) => {
      const next = questions.map((q) => (q.key === key ? {...q, ...patch} : q));
      return 'category' in patch ? groupByCategory(next) : next;
    });
  };

  const expand = (key: string, open: boolean) =>
    setExpanded((current) => {
      const next = new Set(current);
      if (open) {
        next.add(key);
      } else {
        next.delete(key);
      }
      return next;
    });

  const seedIfEmpty = (current: Draft): Draft => {
    if (current.kind !== 'diagnostic' || current.tiers.length > 0) {
      return current;
    }
    const names = t('results.seedNames', {returnObjects: true}) as string[];
    return {
      ...current,
      tiers: seedTiers(maxScores(current.questions, current.kind).total, names),
    };
  };

  const goTo = (target: Step) => {
    if (!canReach(target)) {
      return;
    }
    if (target === 3) {
      setDraft(seedIfEmpty);
    }
    setPreviewTab(target === 1 ? 'cover' : 'final');
    setStep(target);
    window.scrollTo?.({top: 0});
  };

  async function uploadPrompts(): Promise<string[]> {
    return Promise.all(
      draft.prompts.map((prompt) =>
        uploadPromptText(viewer.customerId, prompt.text.trim()),
      ),
    );
  }

  function handleError(error: unknown) {
    if (error instanceof ApiError && error.code === 'SLUG_ALREADY_IN_USE') {
      setSlugInUse(draft.slug);
      setStep(1);
      return;
    }
    if (
      error instanceof ApiError &&
      error.code === 'QUESTIONNAIRE_ALREADY_ANSWERED'
    ) {
      setLocked(true);
      return;
    }
    toast.apiError(error);
  }

  /** Saves the draft; the result (or null when it was not saved) also opens the success screen. */
  async function save(): Promise<SavedResult | null> {
    setConfirmOpen(false);
    if (allIssues.length > 0 || saving) {
      return null;
    }
    setSaving(true);
    try {
      let promptKeys: string[] = [];
      if (draft.kind === 'chaining') {
        try {
          promptKeys = await uploadPrompts();
        } catch (error) {
          if (error instanceof ApiError) {
            throw error;
          }
          toast.error(t('errors.upload'));
          return null;
        }
      }
      const body = encodeFlow(draft, promptKeys);
      let id = questionnaireId;
      if (mode === 'create' || !id) {
        id = await createQuestionnaire(body);
      } else {
        await updateQuestionnaire(id, body);
      }
      void queryClient.invalidateQueries({queryKey: QUESTIONNAIRES_QUERY_KEY});
      void queryClient.invalidateQueries({queryKey: questionnaireQueryKey(id)});
      let slug: string | null = draft.slug.trim() || null;
      try {
        slug = (await fetchQuestionnaire(id)).slug ?? slug;
      } catch {
        // The questionnaire was saved; the link falls back to its id.
      }
      const result = {questionnaireId: id, slug};
      setSaved(result);
      return result;
    } catch (error) {
      handleError(error);
      return null;
    } finally {
      setSaving(false);
    }
  }

  /** The main action: Continue on steps 1–2; then Create (after confirming) or Save changes. */
  const primary = () => {
    if (step < 3) {
      goTo((step + 1) as Step);
      return;
    }
    if (mode === 'create') {
      setConfirmOpen(true);
      return;
    }
    void save();
  };

  const primaryIssues = step < 3 ? issuesOf(step) : allIssues;

  return {
    draft,
    mode,
    questionnaireId,
    step,
    issuesOf,
    allIssues,
    message,
    canReach,
    goTo,
    back: () => (step > 1 ? goTo((step - 1) as Step) : undefined),
    primary,
    primaryDisabledReason: message(primaryIssues[0]),
    confirmOpen,
    closeConfirm: () => setConfirmOpen(false),
    save,
    saving,
    saved,
    keepEditing: () => setSaved(null),
    editPath: (id: string) => {
      const kind = editRouteKind(draft.kind);
      return `/questionnaires/${id}/edit${kind ? `/${kind}` : ''}`;
    },
    locked,
    slugInUse,
    flagged,
    flag: setFlagged,
    previewOpen,
    previewTab,
    setPreviewTab,
    togglePreview: () => setPreviewOpen((open) => !open),
    closePreview: () => setPreviewOpen(false),
    update,
    // Questions
    expanded,
    expand,
    selected,
    selectedIndex,
    selectQuestion: setSelectedKey,
    problemKey,
    showProblem: () => {
      if (problemKey) {
        setSelectedKey(problemKey);
        setFlagged(problemKey);
      }
    },
    canMove: (key: string, delta: -1 | 1) => neighbour(key, delta) !== null,
    moveBy: (key: string, delta: -1 | 1) => {
      const target = neighbour(key, delta);
      if (target === null) {
        return;
      }
      setQuestions((questions) => {
        const next = [...questions];
        const from = next.findIndex((q) => q.key === key);
        [next[from], next[target]] = [next[target]!, next[from]!];
        return next;
      });
    },
    addQuestion: (category = '') => {
      const question = newQuestion(draft.kind, category);
      setQuestions((questions) => groupByCategory([...questions, question]));
      expand(question.key, true);
      setSelectedKey(question.key);
    },
    duplicateQuestion: (key: string) =>
      setQuestions((questions) => {
        const index = questions.findIndex((q) => q.key === key);
        const original = questions[index];
        if (!original) {
          return questions;
        }
        const copy = duplicateQuestion(original);
        expand(copy.key, true);
        setSelectedKey(copy.key);
        return [
          ...questions.slice(0, index + 1),
          copy,
          ...questions.slice(index + 1),
        ];
      }),
    deleteQuestion: (key: string) => {
      if (draft.questions.length <= 1) {
        return;
      }
      const index = draft.questions.findIndex((q) => q.key === key);
      const rest = draft.questions.filter((q) => q.key !== key);
      if (key === selected?.key) {
        setSelectedKey(rest[Math.min(index, rest.length - 1)]?.key ?? null);
      }
      setQuestions((questions) => questions.filter((q) => q.key !== key));
    },
    updateQuestion,
    setQuestionType: (key: string, type: FieldType) => {
      setFlagged((current) => (current === key ? null : current));
      setQuestions((questions) =>
        questions.map((q) =>
          q.key === key ? withType(q, type, draft.kind) : q,
        ),
      );
    },
    moveQuestion: (
      activeKey: string,
      target: {overKey: string} | {category: string},
    ) =>
      setQuestions((questions) => moveQuestion(questions, activeKey, target)),
    // Diagnostic
    reseedTiers: () => {
      const names = t('results.seedNames', {returnObjects: true}) as string[];
      update({
        tiers: seedTiers(maxScores(draft.questions, draft.kind).total, names),
      });
    },
    addTier: () => {
      const last = draft.tiers.at(-1);
      const from = last ? String(Number(last.max) + 1) : '0';
      update({
        tiers: [
          ...draft.tiers,
          {
            key: newKey('t'),
            id: newKey('tier'),
            name: '',
            description: '',
            min: Number.isFinite(Number(from)) ? from : '',
            max: '',
          },
        ],
      });
    },
    // Chaining
    addPrompt: () =>
      draft.prompts.length < MAX_PROMPTS &&
      update({prompts: [...draft.prompts, {key: newKey('p'), text: ''}]}),
  };
}

export type Editor = ReturnType<typeof useEditor>;
