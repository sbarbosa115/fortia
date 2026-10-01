import {
  assignationQueryKey,
  fetchAssignation,
} from '@console/entities/assignation';
import {isApiError} from '@shared/api';
import {useDocumentTitle} from '@shared/lib';
import {Card, EmptyState, ErrorState, LoadingState} from '@shared/ui';
import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {Link, useParams} from 'react-router';
import {DefaultDetail} from './DefaultDetail';
import {FollowUpDetail} from './FollowUpDetail';
import './assignation-detail.css';

/** /assignations/:id (PRD §10.11): the respondents of a default assignation, or the review of a follow-up. */
export function AssignationDetailPage() {
  const {t} = useTranslation('pages.assignation-detail');
  const {id = ''} = useParams();
  const query = useQuery({
    queryKey: assignationQueryKey(id),
    queryFn: () => fetchAssignation(id),
  });
  useDocumentTitle(`Mappi - ${query.data?.name ?? t('title')}`);

  if (query.isPending) {
    return <LoadingState />;
  }
  if (query.isError) {
    const notFound =
      isApiError(query.error) && [400, 404].includes(query.error.status);
    return (
      <Card>
        {notFound ? (
          <EmptyState
            title={t('notFound')}
            action={
              <Link className="btn btn--secondary" to="/assignations">
                {t('backToList')}
              </Link>
            }
          />
        ) : (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        )}
      </Card>
    );
  }
  return (
    <div className="asg-detail">
      {query.data.type === 'follow_up' ? (
        <FollowUpDetail assignation={query.data} />
      ) : (
        <DefaultDetail assignation={query.data} />
      )}
    </div>
  );
}
