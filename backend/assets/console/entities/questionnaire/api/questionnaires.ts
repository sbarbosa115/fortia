import {api, type Schema} from '@shared/api';

export type QuestionnaireRow = Schema<'QuestionnaireListItemOutput'>;
export type QuestionnairePage = Schema<'QuestionnaireListOutput'>;
export type QuestionnaireTags = Schema<'QuestionnaireTagsOutput'>;

export type ListingType =
  'default' | 'quiz_funnel' | 'diagnostic' | 'process_mapping';
export type SortBy = 'created_at' | 'updated_at';
export type SortOrder = 'asc' | 'desc';

/** The query of GET /questionnaire (PRD §8.4): filters, sort, page and the server-side search (D16). */
export type ListingParams = {
  search: string;
  type: ListingType | null;
  isActive: boolean | null;
  sortBy: SortBy;
  order: SortOrder;
  page: number;
  pageSize: number;
  /** One of the questionnaire's tags, matched whole without case or accents; null or absent = any. */
  tag?: string | null;
};

/** Every cached page of the listing starts with this key: invalidate it after a change to any questionnaire. */
export const QUESTIONNAIRES_QUERY_KEY = ['questionnaires'] as const;

export function questionnairesQueryKey(params: ListingParams) {
  return [...QUESTIONNAIRES_QUERY_KEY, params] as const;
}

export function fetchQuestionnaires(
  params: ListingParams,
): Promise<QuestionnairePage> {
  return api.get<QuestionnairePage>('/questionnaire', {
    query: {
      search: params.search.trim() || undefined,
      type: params.type ?? undefined,
      is_active: params.isActive === null ? undefined : String(params.isActive),
      sort_by: params.sortBy,
      order: params.order,
      page: params.page,
      page_size: params.pageSize,
      tag: params.tag ?? undefined,
    },
  });
}

/** Under the listing's key, so a change to any questionnaire refreshes the tags too. */
export const QUESTIONNAIRE_TAGS_QUERY_KEY = [
  ...QUESTIONNAIRES_QUERY_KEY,
  'tags',
] as const;

/** GET /questionnaire/tags: every tag of the account's questionnaires once, sorted. */
export function fetchQuestionnaireTags(): Promise<QuestionnaireTags> {
  return api.get<QuestionnaireTags>('/questionnaire/tags');
}

/** PATCH /questionnaire/{id}: exactly {is_active}; answers with the updated row. */
export function setQuestionnaireActive(
  id: string,
  isActive: boolean,
): Promise<QuestionnaireRow> {
  return api.patch<QuestionnaireRow>(`/questionnaire/${id}`, {
    is_active: isActive,
  });
}
