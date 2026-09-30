import type {QuestionnaireRow} from '@console/entities/questionnaire';
import {Toggle, Tooltip} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useToggleActive} from '../model/useToggleActive';

/** The State column's switch: labelled with the questionnaire, disabled with the reason for read-only users. */
export function ActiveToggle({
  row,
  disabledReason,
}: {
  row: QuestionnaireRow;
  disabledReason?: string | null;
}) {
  const {t} = useTranslation('features.toggle-questionnaire-active');
  const {mutate} = useToggleActive();
  const toggle = (
    <span className="row">
      <Toggle
        checked={row.is_active}
        hideLabel
        label={t('label', {title: row.title})}
        disabled={Boolean(disabledReason)}
        onChange={(isActive) => mutate({id: row.questionnaire_id, isActive})}
      />
      <span className="muted">
        {row.is_active
          ? t('status.active', {ns: 'shared'})
          : t('status.inactive', {ns: 'shared'})}
      </span>
    </span>
  );
  return disabledReason ? (
    <Tooltip content={disabledReason}>{toggle}</Tooltip>
  ) : (
    toggle
  );
}
