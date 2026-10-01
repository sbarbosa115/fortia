import {splitDuration, type Summary} from '@console/entities/dashboard';
import {Card} from '@shared/ui';
import {useTranslation} from 'react-i18next';

/** Completion rate, sessions, average time and biggest drop-off (PRD §10.9, free on all plans). */
export function SummaryTiles({summary}: {summary: Summary}) {
  const {t} = useTranslation('pages.questionnaire-dashboard');
  const time =
    summary.avgSeconds === null ? null : splitDuration(summary.avgSeconds);
  const drop = summary.biggestDrop;
  return (
    <div className="dash-tiles">
      <Card className="dash-tile">
        <span className="dash-tile__label">{t('summary.completion')}</span>
        <strong className="dash-tile__value">
          {t('percent', {value: summary.completionPct})}
        </strong>
        <span className="dash-tile__hint">
          {t('summary.completedOf', {
            completed: summary.completed,
            total: summary.total,
          })}
        </span>
      </Card>
      <Card className="dash-tile">
        <span className="dash-tile__label">{t('summary.sessions')}</span>
        <strong className="dash-tile__value">{summary.total}</strong>
      </Card>
      <Card className="dash-tile">
        <span className="dash-tile__label">{t('summary.avgTime')}</span>
        <strong className="dash-tile__value">
          {time ? t('duration', time) : t('none')}
        </strong>
      </Card>
      <Card className="dash-tile">
        <span className="dash-tile__label">{t('summary.biggestDrop')}</span>
        <strong className="dash-tile__value">
          {drop === null
            ? t('none')
            : drop.index === null
              ? t('summary.atEnd')
              : t('questionShort', {n: drop.index + 1})}
        </strong>
        {drop ? (
          <span className="dash-tile__hint">
            {t('summary.dropPct', {value: drop.pct})}
          </span>
        ) : null}
      </Card>
    </div>
  );
}
