import {ContactSalesModal} from '@console/features/contact-sales';
import {formatDate, formatMoney, useDocumentTitle} from '@shared/lib';
import {
  Button,
  ConfirmDialog,
  EmptyState,
  ErrorState,
  IconButton,
  Icon,
  LoadingState,
  PageHeader,
} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {CurrentNote} from '../lib/planCards';
import {type PlansPageModel, usePlansPage} from '../model/usePlansPage';
import {PlanCard} from './PlanCard';
import './plans.css';

/** /profile/plans (PRD §10.15, never blocked by the plan). */
export function PlansPage() {
  const {t} = useTranslation('pages.plans');
  const page = usePlansPage();
  useDocumentTitle(`Mappi - ${t('title')}`);

  return (
    <div className="plans-page">
      <PageHeader title={t('title')} subtitle={t('subtitle')} />
      <ReturnNotice page={page} />
      <Content page={page} />
      {page.confirmation !== null ? <Confirm page={page} /> : null}
      {page.contactPlan !== null ? (
        <ContactSalesModal
          planId={page.contactPlan.id}
          planName={page.contactPlan.plan_name}
          defaultEmail={page.viewerEmail}
          onClose={page.closeContact}
        />
      ) : null}
    </div>
  );
}

function Content({page}: {page: PlansPageModel}) {
  const {t} = useTranslation('pages.plans');
  const {t: ts} = useTranslation('shared');
  if (page.loading) {
    return <LoadingState />;
  }
  if (page.error !== null) {
    return <ErrorState error={page.error} onRetry={page.retry} />;
  }
  if (page.cards.length === 0) {
    return <EmptyState title={t('empty.title')} body={t('empty.body')} />;
  }
  const disabledReason = page.canWrite ? null : ts('readOnly.change');
  return (
    <>
      <p className="muted plans-page__hint">{t('promoHint')}</p>
      <div className="plans-grid">
        {page.cards.map((card) => (
          <PlanCard
            key={card.plan.id}
            card={card}
            locale={page.locale}
            busyKey={page.busyKey}
            disabledReason={disabledReason}
            onChoose={page.choose}
          >
            {card.current !== null ? (
              <CurrentDetails page={page} disabledReason={disabledReason} />
            ) : null}
          </PlanCard>
        ))}
      </div>
    </>
  );
}

/** The notes and the subscription actions on the current plan's card. */
function CurrentDetails({
  page,
  disabledReason,
}: {
  page: PlansPageModel;
  disabledReason: string | null;
}) {
  const {t} = useTranslation('pages.plans');
  const cancelling = page.data?.cancel_at_period_end ?? false;
  const scheduled = Boolean(page.data?.scheduled_plan_id);
  return (
    <>
      {page.notes.map((note) => (
        <p className="plan-card__note" key={note.kind}>
          <NoteText note={note} locale={page.locale} />
        </p>
      ))}
      {page.hasSubscription ? (
        <div className="plan-card__subscription">
          {scheduled ? (
            <Button
              size="sm"
              loading={page.busyKey === 'revert'}
              disabled={page.busy && page.busyKey !== 'revert'}
              disabledReason={disabledReason}
              onClick={page.revert}
            >
              {t('actions.keepCurrent')}
            </Button>
          ) : null}
          {cancelling ? (
            <Button
              size="sm"
              variant="primary"
              loading={page.busyKey === 'resume'}
              disabled={page.busy && page.busyKey !== 'resume'}
              disabledReason={disabledReason}
              onClick={page.resume}
            >
              {t('actions.resume')}
            </Button>
          ) : (
            <Button
              size="sm"
              variant="ghost"
              disabled={page.busy}
              disabledReason={disabledReason}
              onClick={page.askCancel}
            >
              {t('actions.cancel')}
            </Button>
          )}
        </div>
      ) : null}
    </>
  );
}

function NoteText({note, locale}: {note: CurrentNote; locale: string}) {
  const {t} = useTranslation('pages.plans');
  if (note.kind === 'discount') {
    const {discount} = note;
    const value =
      discount.percent_off != null
        ? `${discount.percent_off}%`
        : formatMoney(
            discount.amount_off ?? 0,
            discount.currency ?? 'usd',
            locale,
          );
    if (discount.duration === 'forever') {
      return <>{t('notes.discountForever', {value})}</>;
    }
    return discount.ends_at ? (
      <>
        {t('notes.discountUntil', {
          value,
          date: formatDate(discount.ends_at, locale),
        })}
      </>
    ) : (
      <>{t('notes.discount', {value})}</>
    );
  }
  const date = formatDate(note.date, locale);
  if (note.kind === 'daysLeft') {
    return <>{t('notes.daysLeft', {count: note.days, date})}</>;
  }
  return <>{t(`notes.${note.kind}`, {date})}</>;
}

/** "Checkout completed" / "Checkout canceled" after coming back from the gateway. */
function ReturnNotice({page}: {page: PlansPageModel}) {
  const {t} = useTranslation('pages.plans');
  if (page.notice === null) {
    return null;
  }
  return (
    <div className={`plans-notice plans-notice--${page.notice}`} role="status">
      <Icon name={page.notice === 'success' ? 'check' : 'info'} size={18} />
      <p>{t(`notice.${page.notice}`)}</p>
      <IconButton
        size="sm"
        label={t('notice.dismiss')}
        icon={<Icon name="close" size={14} />}
        onClick={page.dismissNotice}
      />
    </div>
  );
}

function Confirm({page}: {page: PlansPageModel}) {
  const {t} = useTranslation('pages.plans');
  const confirmation = page.confirmation;
  if (confirmation === null) {
    return null;
  }
  const until = formatDate(page.data?.active_until, page.locale);
  if (confirmation.kind === 'cancel') {
    return (
      <ConfirmDialog
        open
        danger
        title={t('confirm.cancel.title')}
        body={t('confirm.cancel.body', {
          plan: page.currentPlan?.plan_name ?? '',
          date: until,
        })}
        confirmLabel={t('confirm.cancel.confirm')}
        cancelLabel={t('confirm.cancel.keep')}
        loading={page.confirming}
        onConfirm={page.confirm}
        onCancel={page.closeConfirmation}
      />
    );
  }
  return (
    <ConfirmDialog
      open
      title={t(`confirm.${confirmation.kind}.title`)}
      body={t(`confirm.${confirmation.kind}.body`, {
        plan: confirmation.plan.plan_name,
        current: page.currentPlan?.plan_name ?? '',
        date: until,
      })}
      confirmLabel={t(`confirm.${confirmation.kind}.confirm`)}
      loading={page.confirming}
      onConfirm={page.confirm}
      onCancel={page.closeConfirmation}
    />
  );
}
