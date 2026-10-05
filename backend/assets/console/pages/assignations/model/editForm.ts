import type {ProjectPayload} from '@console/entities/project';

/** A questionnaire added in the dialog: it becomes a new follow-up of the assignation on save. */
export type AddedQuestionnaire = {
  questionnaireId: string;
  title: string;
  /** Whether it goes to review once completed. */
  review: boolean;
};

/** What the edit dialog holds (PRD §10.12): the organization is read-only, so it is not part of it. */
export type EditDraft = {
  name: string;
  description: string;
  dueDate: string;
  /** Its questionnaires (follow-ups) that go to review once completed; the others are simply completed. */
  reviewIds: string[];
  /** Every follow-up it had when the dialog opened. */
  assignationIds: string[];
  /** Those of them taken out: unlinked on save, their answers kept. */
  removedIds: string[];
  /** Questionnaires to add as new follow-ups. */
  added: AddedQuestionnaire[];
};

/** Keys of `edit.errors.*` in the page's translations. */
export type EditErrors = Partial<
  Record<
    'name' | 'description' | 'dueDate',
    | 'nameRequired'
    | 'nameTooLong'
    | 'descriptionTooLong'
    | 'deadlineRequired'
    | 'deadlineInvalid'
  >
>;

const DATE = /^(\d{4})-(\d{2})-(\d{2})$/;

export function draftFrom(project: {
  name: string;
  description?: string | null;
  due_date?: string | null;
  assignations?: {assignations_id: string; requires_review?: boolean}[];
}): EditDraft {
  return {
    name: project.name,
    description: project.description ?? '',
    dueDate: project.due_date ?? '',
    reviewIds: (project.assignations ?? [])
      .filter((item) => item.requires_review ?? true)
      .map((item) => item.assignations_id),
    assignationIds: (project.assignations ?? []).map(
      (item) => item.assignations_id,
    ),
    removedIds: [],
    added: [],
  };
}

/** Takes one of its follow-ups out of the draft, or puts it back. */
export function withRemoved(
  draft: EditDraft,
  id: string,
  removed: boolean,
): EditDraft {
  const others = draft.removedIds.filter((item) => item !== id);
  return {...draft, removedIds: removed ? [...others, id] : others};
}

/** Adds a questionnaire (once); it goes to review unless switched off. */
export function withAdded(
  draft: EditDraft,
  questionnaire: {questionnaireId: string; title: string},
): EditDraft {
  if (
    draft.added.some(
      (item) => item.questionnaireId === questionnaire.questionnaireId,
    )
  ) {
    return draft;
  }
  return {...draft, added: [...draft.added, {...questionnaire, review: true}]};
}

/** Drops a questionnaire added in the dialog. */
export function withoutAdded(
  draft: EditDraft,
  questionnaireId: string,
): EditDraft {
  return {
    ...draft,
    added: draft.added.filter(
      (item) => item.questionnaireId !== questionnaireId,
    ),
  };
}

/** Switches review on or off for one added questionnaire. */
export function withAddedReview(
  draft: EditDraft,
  questionnaireId: string,
  on: boolean,
): EditDraft {
  return {
    ...draft,
    added: draft.added.map((item) =>
      item.questionnaireId === questionnaireId ? {...item, review: on} : item,
    ),
  };
}

/** Switches review on or off for one questionnaire of the draft. */
export function withReview(
  draft: EditDraft,
  id: string,
  on: boolean,
): EditDraft {
  const others = draft.reviewIds.filter((item) => item !== id);
  return {...draft, reviewIds: on ? [...others, id] : others};
}

function isRealDate(value: string): boolean {
  const match = DATE.exec(value);
  if (!match) {
    return false;
  }
  const [year, month, day] = [match[1], match[2], match[3]].map(Number);
  const date = new Date(Date.UTC(year ?? 0, (month ?? 1) - 1, day ?? 1));
  return (
    date.getUTCFullYear() === year &&
    date.getUTCMonth() === (month ?? 0) - 1 &&
    date.getUTCDate() === day
  );
}

/** Name required ≤ 200, description ≤ 2000, deadline required and a real date (it can move, never be cleared). */
export function editErrors(draft: EditDraft): EditErrors {
  const errors: EditErrors = {};
  const name = draft.name.trim();
  if (name === '') {
    errors.name = 'nameRequired';
  } else if (Array.from(name).length > 200) {
    errors.name = 'nameTooLong';
  }
  if (Array.from(draft.description.trim()).length > 2000) {
    errors.description = 'descriptionTooLong';
  }
  if (draft.dueDate.trim() === '') {
    errors.dueDate = 'deadlineRequired';
  } else if (!isRealDate(draft.dueDate.trim())) {
    errors.dueDate = 'deadlineInvalid';
  }
  return errors;
}

/**
 * PUT /projects/{id}: review is set on each of its questionnaires; assignation_ids (the ones kept) only when some were
 * taken out; questionnaire_ids, with the ones that go to review and the registration slide's title, only when some
 * were added.
 */
export function toPayload(
  draft: EditDraft,
  registrationTitle: string,
): ProjectPayload {
  const description = draft.description.trim();
  const kept = draft.assignationIds.filter(
    (id) => !draft.removedIds.includes(id),
  );
  return {
    name: draft.name.trim(),
    description: description === '' ? null : description,
    due_date: draft.dueDate.trim(),
    review_assignation_ids: draft.reviewIds.filter((id) => kept.includes(id)),
    ...(kept.length < draft.assignationIds.length
      ? {assignation_ids: kept}
      : {}),
    ...(draft.added.length > 0
      ? {
          questionnaire_ids: draft.added.map((item) => item.questionnaireId),
          review_questionnaire_ids: draft.added
            .filter((item) => item.review)
            .map((item) => item.questionnaireId),
          registration_title: registrationTitle,
        }
      : {}),
  };
}
