import {
  biggestDrop,
  groupFunnel,
  type FunnelRow,
  type FunnelStep,
} from '@console/entities/dashboard';
import {Button, Card, CardBody, CardHeader} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';

/** Started → each question → Completed, with runs of no drop-off grouped past 6 questions (PRD §10.9). */
export function FunnelCard({
  steps,
  titles,
}: {
  steps: FunnelStep[];
  titles: Record<string, string>;
}) {
  const {t} = useTranslation('pages.questionnaire-dashboard');
  const [showAll, setShowAll] = useState(false);
  const rows = groupFunnel(steps, showAll);
  const questions = steps.filter((s) => s.kind === 'question').length;
  const drop = biggestDrop(steps);

  const label = (row: FunnelRow): string => {
    switch (row.kind) {
      case 'started':
        return t('funnel.started');
      case 'completed':
        return t('funnel.completed');
      case 'group':
        return t('funnel.group', {from: row.from + 1, to: row.to + 1});
      default:
        return t('questionShort', {n: row.index + 1});
    }
  };

  return (
    <Card>
      <CardHeader
        title={t('funnel.title')}
        actions={
          questions > 6 ? (
            <Button
              size="sm"
              variant="ghost"
              onClick={() => setShowAll((v) => !v)}
            >
              {showAll
                ? t('funnel.showLess')
                : t('funnel.showAll', {count: questions})}
            </Button>
          ) : null
        }
      />
      <CardBody>
        <p className="muted dash-funnel__message">
          {drop === null
            ? t('funnel.noDrop')
            : drop.index === null
              ? t('funnel.dropAtEnd')
              : t('funnel.dropAt', {n: drop.index + 1})}
        </p>
        <ol className="dash-funnel">
          {rows.map((row, i) => (
            <li key={i} className="dash-funnel__row">
              <span
                className="dash-funnel__label"
                title={
                  row.kind === 'question' ? titles[row.questionId] : undefined
                }
              >
                {label(row)}
              </span>
              <span className="dash-funnel__track" aria-hidden>
                <span
                  className="dash-funnel__bar"
                  style={{width: `${row.pct}%`}}
                />
              </span>
              <span className="dash-funnel__value">
                {t('funnel.value', {count: row.count, pct: row.pct})}
              </span>
            </li>
          ))}
        </ol>
      </CardBody>
    </Card>
  );
}
