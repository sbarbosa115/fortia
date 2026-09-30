import {publicFlowUrl} from '@shared/config';
import type {QuestionnaireRow} from '../api/questionnaires';

/** What a row shows in the Type column (PRD §10.6). */
export type QuestionnaireKind =
  'default' | 'quiz_funnel' | 'diagnostic' | 'process_mapping' | 'chain';

/**
 * A chain first; else the kind of its result (on_completed.type); else its own type (a quiz funnel or diagnostic);
 * else a standard questionnaire. The same rule as the listing's type filter on the server.
 */
export function questionnaireKind(row: {
  is_chain: boolean;
  on_completed?: Pick<
    NonNullable<QuestionnaireRow['on_completed']>,
    'type'
  > | null;
  type: string;
}): QuestionnaireKind {
  if (row.is_chain) {
    return 'chain';
  }
  const result = row.on_completed?.type;
  if (result) {
    return result;
  }
  if (row.type === 'diagnostic') {
    return 'diagnostic';
  }
  if (row.type === 'quiz_funnel' || row.type === 'ecommerce') {
    return 'quiz_funnel';
  }
  return 'default';
}

/** The respondent link of a questionnaire: /f/{slug}, or /f/{questionnaire_id} when it has no slug. */
export function questionnairePublicUrl(
  row: Pick<QuestionnaireRow, 'slug' | 'questionnaire_id'>,
): string {
  return publicFlowUrl(row.slug || row.questionnaire_id);
}
