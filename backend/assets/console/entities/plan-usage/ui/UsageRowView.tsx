import {ProgressBar} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {type UsageRow, usageTone} from '../lib/usage';
import './plan-usage.css';

/** One usage bar: the row's name, "3 of 5" (or Unlimited / Not included) and a coloured bar (PRD §10.14). */
export function UsageRowView({row}: {row: UsageRow}) {
  const {t} = useTranslation('entities.plan-usage');
  const name = t(`rows.${row.key}`, {defaultValue: row.key});
  const tone = usageTone(row.percent);
  let amount: string;
  if (!row.included) {
    amount = t('usage.notIncluded');
  } else if (row.unlimited) {
    amount = t('usage.usedUnlimited', {used: row.used});
  } else {
    amount = t('usage.ofLimit', {used: row.used, limit: row.limit});
  }
  return (
    <div className="usage-row">
      <div className="usage-row__head">
        <span className="usage-row__name">{name}</span>
        <span className="usage-row__amount muted">{amount}</span>
      </div>
      {row.included && !row.unlimited ? (
        <ProgressBar
          value={row.percent}
          label={t('usage.barLabel', {name, percent: row.percent})}
          tone={tone === 'success' ? undefined : tone}
        />
      ) : null}
    </div>
  );
}
