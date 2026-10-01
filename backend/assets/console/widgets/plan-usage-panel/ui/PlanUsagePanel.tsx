import {UsageRowView} from '@console/entities/plan-usage';
import {formatDate} from '@shared/lib';
import {
  Badge,
  Button,
  Card,
  CardBody,
  CardHeader,
  EmptyState,
  ErrorState,
  LoadingState,
} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {usePlanUsagePanel} from '../model/usePlanUsagePanel';
import './plan-usage-panel.css';

/** Profile → "Plan & usage" (PRD §10.14). */
export function PlanUsagePanel() {
  const {t, i18n} = useTranslation('widgets.plan-usage-panel');
  const {t: ts} = useTranslation('shared');
  const panel = usePlanUsagePanel();

  if (panel.loading) {
    return <LoadingState />;
  }
  if (panel.error) {
    return <ErrorState error={panel.error} onRetry={panel.retry} />;
  }
  if (!panel.planName) {
    return (
      <Card>
        <EmptyState
          title={t('noPlan.title')}
          body={t('noPlan.body')}
          action={
            <Link className="btn btn--primary" to="/profile/plans">
              {t('choosePlan')}
            </Link>
          }
        />
      </Card>
    );
  }

  const manage = panel.hasSubscription ? (
    <Button
      onClick={panel.openPortal}
      loading={panel.openingPortal}
      disabledReason={panel.canWrite ? null : ts('readOnly.change')}
    >
      {t('manageBilling')}
    </Button>
  ) : null;

  return (
    <Card className="plan-usage">
      <CardHeader
        title={
          <span className="row">
            {panel.planName}
            <Badge tone={panel.active ? 'success' : 'danger'}>
              {panel.active ? t('active') : t('expired')}
            </Badge>
          </span>
        }
        actions={
          <>
            {manage}
            <Link className="btn btn--primary" to="/profile/plans">
              {panel.active ? t('changePlan') : t('choosePlan')}
            </Link>
          </>
        }
      />
      <CardBody>
        {panel.until ? (
          <p className="muted plan-usage__period">
            {t(panel.active ? 'period.until' : 'period.ended', {
              date: formatDate(panel.until, i18n.language),
            })}
          </p>
        ) : null}
        <div className="plan-usage__rows">
          {panel.rows.map((row) => (
            <UsageRowView key={row.key} row={row} />
          ))}
        </div>
      </CardBody>
    </Card>
  );
}
