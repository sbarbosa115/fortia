import {
  type Assignation,
  AudienceChip,
  DueDate,
  ReviewStatusBadge,
  useCopyLink,
} from '@console/entities/assignation';
import {useViewer} from '@console/entities/viewer';
import {Badge, Button, Icon, PageHeader} from '@shared/ui';
import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';

/**
 * The detail's header (PRD §10.11): the name, a summary line, the type badge, the audience, the review state and
 * due date of a follow-up, Copy link, Edit and the variant's own actions.
 */
export function DetailHeader({
  assignation,
  subtitle,
  actions,
}: {
  assignation: Assignation;
  subtitle: ReactNode;
  actions?: ReactNode;
}) {
  const {t} = useTranslation('pages.assignation-detail');
  const {t: tEntity} = useTranslation('entities.assignation');
  const {t: tShared} = useTranslation('shared');
  const viewer = useViewer();
  const copyLink = useCopyLink();
  const followUp = assignation.type === 'follow_up';

  return (
    <>
      <Link className="asg-detail__back" to="/assignations">
        <Icon name="chevron-left" size={16} />
        {t('back')}
      </Link>
      <PageHeader
        title={assignation.name}
        subtitle={subtitle}
        actions={
          <>
            <Button
              icon={<Icon name="copy" size={16} />}
              onClick={() => copyLink(assignation.questionnaire_url)}
            >
              {t('copyLink')}
            </Button>
            {viewer.canWrite ? (
              <Link
                className="btn btn--secondary"
                to={`/assignations/${assignation.assignations_id}/edit`}
              >
                <Icon name="edit" size={16} />
                {t('edit')}
              </Link>
            ) : (
              <Button
                icon={<Icon name="edit" size={16} />}
                disabledReason={tShared('readOnly.change')}
              >
                {t('edit')}
              </Button>
            )}
            {actions}
          </>
        }
      />
      <div className="asg-detail__badges">
        <Badge tone={followUp ? 'accent' : 'neutral'}>
          {tEntity(`type.${assignation.type}`)}
        </Badge>
        <AudienceChip audience={assignation.audience} />
        {followUp && assignation.completed && assignation.review_status ? (
          <ReviewStatusBadge status={assignation.review_status} />
        ) : null}
        {followUp ? (
          <DueDate
            dueDate={assignation.due_date}
            completed={assignation.completed}
          />
        ) : null}
      </div>
    </>
  );
}
