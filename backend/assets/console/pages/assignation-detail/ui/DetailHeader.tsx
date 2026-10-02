import {
  type Assignation,
  AudienceChip,
  DueDate,
  ReviewStatusBadge,
  useCopyLink,
} from '@console/entities/assignation';
import {useViewer} from '@console/entities/viewer';
import {useBackTo, useHere, withFrom} from '@shared/lib';
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
  const backTo = useBackTo();
  const here = useHere();

  return (
    <>
      <Link className="asg-detail__back" to={backTo?.to ?? '/assignations'}>
        <Icon name="chevron-left" size={16} />
        {backTo ? tShared(`back.${backTo.kind}`) : t('back')}
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
                to={withFrom(
                  `/questionnaires/${assignation.questionnaire_id}/edit`,
                  here,
                )}
              >
                <Icon name="clipboard-list" size={16} />
                {t('editQuestionnaire')}
              </Link>
            ) : (
              <Button
                icon={<Icon name="clipboard-list" size={16} />}
                disabledReason={tShared('readOnly.change')}
              >
                {t('editQuestionnaire')}
              </Button>
            )}
            {viewer.canWrite ? (
              <Link
                className="btn btn--secondary"
                to={withFrom(
                  `/assignations/${assignation.assignations_id}/edit`,
                  here,
                )}
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
