import {
  QUESTIONNAIRES_QUERY_KEY,
  type QuestionnairePage,
  setQuestionnaireActive,
} from '@console/entities/questionnaire';
import {useToast} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';

type Variables = {id: string; isActive: boolean};
type Snapshot = [readonly unknown[], QuestionnairePage | undefined][];

/**
 * The Active toggle of the listing (PRD §10.6): optimistic — every cached page shows the new state at once — and
 * reverted, with the API's message, if the save fails.
 */
export function useToggleActive() {
  const queryClient = useQueryClient();
  const toast = useToast();
  return useMutation<unknown, unknown, Variables, {snapshot: Snapshot}>({
    mutationFn: ({id, isActive}) => setQuestionnaireActive(id, isActive),
    onMutate: async ({id, isActive}) => {
      await queryClient.cancelQueries({queryKey: QUESTIONNAIRES_QUERY_KEY});
      const snapshot = queryClient.getQueriesData<QuestionnairePage>({
        queryKey: QUESTIONNAIRES_QUERY_KEY,
      });
      queryClient.setQueriesData<QuestionnairePage>(
        {queryKey: QUESTIONNAIRES_QUERY_KEY},
        // Only the listing's pages: the account's tags live under the same key.
        (page) =>
          page && Array.isArray(page.items)
            ? {
                ...page,
                items: page.items.map((row) =>
                  row.questionnaire_id === id
                    ? {
                        ...row,
                        is_active: isActive,
                        status: isActive ? 'active' : 'inactive',
                      }
                    : row,
                ),
              }
            : page,
      );
      return {snapshot};
    },
    onError: (error, _variables, context) => {
      for (const [key, page] of context?.snapshot ?? []) {
        queryClient.setQueryData(key, page);
      }
      toast.apiError(error);
    },
    onSettled: () =>
      queryClient.invalidateQueries({queryKey: QUESTIONNAIRES_QUERY_KEY}),
  });
}
