import {useAccountBrand, usePageViews} from '@respondent/entities/account';
import {fetchResults, recallResult} from '@respondent/entities/session';
import {StatusScreen} from '@respondent/widgets/questionnaire-runner';
import {
  fromResults,
  fromSubmission,
  ResultsView,
} from '@respondent/widgets/results-view';
import {useDocumentTitle} from '@shared/lib';
import {LoadingState} from '@shared/ui';
import {useQuery} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Navigate, useParams} from 'react-router';

/**
 * The results (PRD §9.12): /session/:sessionId/results is the canonical, reloadable link — the result in memory
 * when the respondent just submitted, else GET …/results with the brand of its account; /results and
 * /q/:id/results are the legacy in-memory links. If nothing can be shown, back to /.
 */
export function ResultsPage() {
  const {t} = useTranslation('pages.results');
  const {sessionId = null} = useParams();
  const [memory] = useState(() => recallResult(sessionId));
  const query = useQuery({
    queryKey: ['respondent', 'results', sessionId],
    queryFn: () => fetchResults(sessionId ?? ''),
    enabled: memory === null && sessionId !== null,
    staleTime: Infinity,
    retry: false,
  });
  const data = memory
    ? fromSubmission(memory.sessionId, memory.customerId, memory.result)
    : query.data
      ? fromResults(query.data)
      : null;
  const brand = useAccountBrand(data?.customerId ?? null);
  usePageViews(true);
  useDocumentTitle(t('documentTitle'));

  if ((memory === null && sessionId === null) || query.isError) {
    return <Navigate to="/" replace />;
  }
  if (!data || !brand.ready) {
    return (
      <div className="boot-skeleton">
        {data ? (
          <LoadingState />
        ) : (
          <StatusScreen
            busy
            title={t('loading.title')}
            subtitle={t('loading.subtitle')}
          />
        )}
      </div>
    );
  }
  return (
    <div className="results-page">
      <ResultsView data={data} />
    </div>
  );
}
