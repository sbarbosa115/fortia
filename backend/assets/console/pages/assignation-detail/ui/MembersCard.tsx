import {Button, Card, CardHeader, ErrorState, LoadingState} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useRespondents} from '../model/useRespondents';

/** "Who can carry it on (N)" (PRD §10.11): the follow-up's audience, any of whom may continue the shared session. */
export function MembersCard({
  assignationId,
  total,
}: {
  assignationId: string;
  total: number;
}) {
  const {t} = useTranslation('pages.assignation-detail');
  const {query, rows, hasMore} = useRespondents(assignationId);
  let body;
  if (query.isPending) {
    body = <LoadingState />;
  } else if (query.isError) {
    body = <ErrorState error={query.error} onRetry={() => query.refetch()} />;
  } else if (rows.length === 0) {
    body = (
      <p className="asg-detail__none muted">{t('followUp.membersEmpty')}</p>
    );
  } else {
    body = (
      <ul className="asg-detail__members">
        {rows.map((row) => (
          <li key={row.organization_user_id}>
            <strong>{row.organization_user_name}</strong>
            <span className="muted">
              {row.organization_user_email ?? t('respondents.noEmail')}
            </span>
          </li>
        ))}
      </ul>
    );
  }
  return (
    <Card>
      <CardHeader title={t('followUp.members', {count: total})} />
      {body}
      {hasMore ? (
        <div className="asg-detail__more">
          <Button
            size="sm"
            loading={query.isFetchingNextPage}
            onClick={() => void query.fetchNextPage()}
          >
            {t('respondents.loadMore')}
          </Button>
        </div>
      ) : null}
    </Card>
  );
}
