import {useDocumentTitle} from '@shared/lib';
import {Button, Icon, PageHeader, Tooltip} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {STEPS} from '../model/quizFunnel';
import {useQuizFunnel} from '../model/useQuizFunnel';
import {FunnelSuccess} from './FunnelSuccess';
import {GenerateStep} from './GenerateStep';
import {ProductsStep} from './ProductsStep';
import {StoreStep} from './StoreStep';
import './quiz-funnel.css';

/**
 * /questionnaires/create/quizfunnel (PRD §10.5 Quiz Funnel, feature quiz-funnel): Store → Products → Generate, then
 * the new funnel's link (and, for a Shopify store, how to enable the app embed in its theme).
 */
export function QuizFunnelCreatePage() {
  const {t} = useTranslation('pages.quiz-funnel-create');
  const state = useQuizFunnel();
  useDocumentTitle(`Mappi - ${t('title')}`);

  if (state.result) {
    return <FunnelSuccess result={state.result} shop={state.shop} />;
  }

  const last = state.step === 'generate';
  const continueReason =
    state.step === 'store' && !state.storeReady
      ? state.source === 'website'
        ? t('errors.invalidUrl')
        : t('store.connectFirst')
      : state.loadingProducts
        ? t('products.wait')
        : null;

  return (
    <div className="quiz-funnel">
      <nav className="quiz-funnel__crumbs" aria-label={t('breadcrumb')}>
        <Link to="/questionnaires/new">{t('crumbNew')}</Link>
        <span aria-hidden>/</span>
        <span>{t('title')}</span>
      </nav>
      <PageHeader
        title={t('title')}
        subtitle={t('subtitle')}
        actions={
          <>
            {state.stepIndex > 0 ? (
              <Button
                icon={<Icon name="chevron-left" size={16} />}
                disabled={state.generating || state.loadingProducts}
                onClick={state.back}
              >
                {t('back')}
              </Button>
            ) : null}
            {last ? null : (
              <Button
                variant="primary"
                disabledReason={continueReason}
                onClick={state.next}
              >
                {t('continue')}
              </Button>
            )}
          </>
        }
      />
      <ol className="quiz-funnel__stepper" aria-label={t('steps.label')}>
        {STEPS.map((step, index) => {
          const reachable = state.reachable(step);
          const button = (
            <button
              type="button"
              className="quiz-funnel__step-button"
              aria-current={state.step === step ? 'step' : undefined}
              disabled={!reachable}
              onClick={() => state.goTo(step)}
            >
              <span className="quiz-funnel__step-number" aria-hidden>
                {index + 1}
              </span>
              <span>{t(`steps.${step}`)}</span>
            </button>
          );
          return (
            <li key={step}>
              {reachable ? (
                button
              ) : (
                <Tooltip content={t('steps.locked')}>{button}</Tooltip>
              )}
            </li>
          );
        })}
      </ol>
      {state.step === 'store' ? <StoreStep state={state} /> : null}
      {state.step === 'products' ? <ProductsStep state={state} /> : null}
      {state.step === 'generate' ? <GenerateStep state={state} /> : null}
    </div>
  );
}
