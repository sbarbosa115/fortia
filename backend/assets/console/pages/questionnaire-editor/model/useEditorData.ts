import {
  fetchFlow,
  fetchHasAnswers,
  fetchPrompts,
  fetchQuestionnaire,
  questionnaireQueryKey,
} from '@console/entities/questionnaire';
import {useQuery} from '@tanstack/react-query';
import {decodeDraft, editorKindOf} from './decode';

/**
 * What /questionnaires/:id/edit loads (PRD §10.7): the questionnaire, its flow (slug, states, cta, layout,
 * result_copy), a chain's prompts, and whether it has answers (then it is Locked). Answers the editor kind and the
 * draft to edit.
 */
export function useEditorData(id: string) {
  const query = useQuery({
    queryKey: [...questionnaireQueryKey(id), 'editor'],
    queryFn: async () => {
      const [questionnaire, flow, hasAnswers] = await Promise.all([
        fetchQuestionnaire(id),
        fetchFlow(id).catch(() => null),
        fetchHasAnswers(id),
      ]);
      const kind = editorKindOf(questionnaire, flow);
      const prompts = kind === 'chaining' ? await fetchPrompts(id) : [];
      return {
        questionnaire,
        flow,
        kind,
        locked: hasAnswers,
        draft: decodeDraft(questionnaire, flow, prompts, kind),
      };
    },
    staleTime: 0,
    gcTime: 0,
  });
  return query;
}
