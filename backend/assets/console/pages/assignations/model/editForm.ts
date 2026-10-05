import type {ProjectPayload} from '@console/entities/project';

/** What the edit dialog holds (PRD §10.12): the organization is read-only, so it is not part of it. */
export type EditDraft = {
  name: string;
  description: string;
  dueDate: string;
  /** Its questionnaires (follow-ups) that go to review once completed; the others are simply completed. */
  reviewIds: string[];
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

/** PUT /projects/{id}: its questionnaires (assignation_ids) are left as they are; review is set on each one. */
export function toPayload(draft: EditDraft): ProjectPayload {
  const description = draft.description.trim();
  return {
    name: draft.name.trim(),
    description: description === '' ? null : description,
    due_date: draft.dueDate.trim(),
    review_assignation_ids: draft.reviewIds,
  };
}
