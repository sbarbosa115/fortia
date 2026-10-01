import type {BillingInterval, CatalogPlan} from '@console/entities/billing';
import {formatDate, formatMoney, joinClasses} from '@shared/lib';
import {Badge, Button} from '@shared/ui';
import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import type {PlanCardView} from '../lib/planCards';

type Props = {
  card: PlanCardView;
  locale: string;
  /** The notes and subscription actions of the current plan's card. */
  children?: ReactNode;
  busyKey: string | null;
  disabledReason: string | null;
  onChoose: (plan: CatalogPlan, interval: BillingInterval) => void;
};

const MONTHLY_LABEL = {
  subscribe: 'actions.subscribe',
  upgrade: 'actions.upgrade',
  switch: 'actions.switch',
  contact: 'actions.contact',
} as const;

/** One plan of /profile/plans (PRD §10.15): price, limits, badges and its buttons. */
export function PlanCard({
  card,
  locale,
  children,
  busyKey,
  disabledReason,
  onChoose,
}: Props) {
  const {t} = useTranslation('pages.plans');
  const {plan} = card;
  const limit = (value: number | null | undefined) =>
    value == null || value < 0
      ? t('limits.unlimited')
      : value.toLocaleString(locale);
  const featureName = (id: string, fallback: string) =>
    t(`rows.${id}`, {ns: 'entities.plan-usage', defaultValue: fallback});
  // The plan you already have and cannot buy online (Starter) needs no "Get in touch".
  const showMonthly =
    card.monthly !== 'current' &&
    !(card.current !== null && card.monthly === 'contact');
  // "Get in touch" is not a billing change: read-only members may use it.
  const reasonFor = (action: string) =>
    action === 'contact' ? null : disabledReason;

  return (
    <article
      className={joinClasses(
        'card',
        'plan-card',
        card.current !== null && 'plan-card--current',
      )}
      aria-labelledby={`plan-${plan.id}`}
    >
      <div className="plan-card__badges">
        {card.current !== null ? (
          <Badge tone="accent">
            {t('badges.current', {
              interval: t(`interval.${card.current}`),
            })}
          </Badge>
        ) : null}
        {card.next !== null ? (
          <Badge tone="warning">{t('badges.next')}</Badge>
        ) : null}
        {card.trialDays !== null ? (
          <Badge tone="success">
            {t('badges.trial', {count: card.trialDays})}
          </Badge>
        ) : null}
      </div>
      <h2 className="plan-card__name serif-heading" id={`plan-${plan.id}`}>
        {plan.plan_name}
      </h2>
      {plan.plan_description ? (
        <p className="muted plan-card__description">{plan.plan_description}</p>
      ) : null}
      <p className="plan-card__price">
        {plan.price_amount == null ? (
          t('price.onRequest')
        ) : (
          <>
            <strong>
              {formatMoney(plan.price_amount, plan.currency, locale)}
            </strong>
            <span className="muted">{t('price.perMonth')}</span>
          </>
        )}
      </p>
      {plan.yearly_price_amount != null ? (
        <p className="muted plan-card__yearly">
          {t('price.yearly', {
            price: formatMoney(plan.yearly_price_amount, plan.currency, locale),
          })}
        </p>
      ) : null}
      {card.next !== null ? (
        <p className="plan-card__note">
          {t('notes.startsOn', {
            date: formatDate(card.next.startsOn, locale),
            interval: t(`interval.${card.next.interval}`),
          })}
        </p>
      ) : null}
      {children}
      <ul className="plan-card__limits">
        <li>
          <span>{t('limits.experiences')}</span>
          <strong>{limit(plan.max_questionnaires)}</strong>
        </li>
        <li>
          <span>{t('limits.responses')}</span>
          <strong>{limit(plan.max_responses)}</strong>
        </li>
        {plan.features
          .filter((feature) => feature.limit !== 0)
          .map((feature) => (
            <li key={feature.feature_id}>
              <span>
                {featureName(feature.feature_id, feature.feature_name)}
              </span>
              <strong>{limit(feature.limit)}</strong>
            </li>
          ))}
      </ul>
      <div className="plan-card__actions">
        {showMonthly ? (
          <Button
            variant={
              card.monthly === 'upgrade' || card.monthly === 'subscribe'
                ? 'primary'
                : 'secondary'
            }
            loading={busyKey === `${plan.id}:month`}
            disabled={busyKey !== null && busyKey !== `${plan.id}:month`}
            disabledReason={reasonFor(card.monthly)}
            onClick={() => onChoose(plan, 'month')}
          >
            {t(MONTHLY_LABEL[card.monthly as keyof typeof MONTHLY_LABEL])}
          </Button>
        ) : null}
        {card.yearly === 'buyYearly' || card.yearly === 'switchYearly' ? (
          <Button
            loading={busyKey === `${plan.id}:year`}
            disabled={busyKey !== null && busyKey !== `${plan.id}:year`}
            disabledReason={disabledReason}
            onClick={() => onChoose(plan, 'year')}
          >
            {t(
              card.yearly === 'buyYearly'
                ? 'actions.buyYearly'
                : 'actions.switchYearly',
            )}
            {card.savePercent !== null ? (
              <span className="plan-card__save">
                {t('actions.save', {percent: card.savePercent})}
              </span>
            ) : null}
          </Button>
        ) : null}
      </div>
    </article>
  );
}
