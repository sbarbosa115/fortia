import {type Assignation, useCopyLink} from '@console/entities/assignation';
import {Icon, IconButton} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';

/** View, Edit, Copy link, Send reminder (follow-ups; disabled once complete) and Delete (PRD §10.11). */
export function RowActions({
  row,
  changeReason,
  onRemind,
  onDelete,
}: {
  row: Assignation;
  changeReason: string | null;
  onRemind: () => void;
  onDelete: () => void;
}) {
  const {t} = useTranslation('pages.assignations');
  const copyLink = useCopyLink();
  const name = row.name;
  return (
    <>
      <Link
        className="btn btn--ghost btn--sm btn--icon"
        to={`/assignations/${row.assignations_id}`}
        aria-label={t('actions.view', {name})}
        title={t('actions.view', {name})}
      >
        <Icon name="eye" />
      </Link>
      {changeReason ? (
        <IconButton
          size="sm"
          icon={<Icon name="edit" />}
          label={t('actions.edit', {name})}
          disabledReason={changeReason}
        />
      ) : (
        <Link
          className="btn btn--ghost btn--sm btn--icon"
          to={`/assignations/${row.assignations_id}/edit`}
          aria-label={t('actions.edit', {name})}
          title={t('actions.edit', {name})}
        >
          <Icon name="edit" />
        </Link>
      )}
      <IconButton
        size="sm"
        icon={<Icon name="copy" />}
        label={t('actions.copyLink', {name})}
        onClick={() => copyLink(row.questionnaire_url)}
      />
      {row.type === 'follow_up' ? (
        <IconButton
          size="sm"
          icon={<Icon name="bell" />}
          label={t('actions.remind', {name})}
          disabledReason={
            changeReason ?? (row.completed ? t('actions.remindDone') : null)
          }
          onClick={onRemind}
        />
      ) : null}
      <IconButton
        size="sm"
        icon={<Icon name="trash" />}
        label={t('actions.delete', {name})}
        disabledReason={changeReason}
        onClick={onDelete}
      />
    </>
  );
}
