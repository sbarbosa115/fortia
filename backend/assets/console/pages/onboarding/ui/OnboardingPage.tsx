import {useDocumentTitle} from '@shared/lib';
import {Button, ProgressBar} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {NAMESPACE, useOnboarding} from '../model/useOnboarding';
import {STEP_COUNT} from '../model/wizard';
import {BuilderStep} from './BuilderStep';
import {GoalStep} from './GoalStep';
import {PublishStep} from './PublishStep';
import {ResultStep} from './ResultStep';
import {TemplateStep} from './TemplateStep';
import {TestStep} from './TestStep';
import {WorkspaceStep} from './WorkspaceStep';
import './onboarding.css';

const STEPS = [1, 2, 3, 4, 5, 6, 7] as const;

/**
 * /onboarding (PRD §10.3): seven steps from the goal to a published, tested questionnaire. "Explore on my own" and
 * every exit of the last step set the onboarding flag first.
 */
export function OnboardingPage() {
  const {t} = useTranslation(NAMESPACE);
  const onboarding = useOnboarding();
  const {state, finish, finishing, finishFailed, canWrite} = onboarding;
  useDocumentTitle(`Mappi - ${t('title')}`);

  return (
    <div className="onboarding">
      <header className="onboarding__header">
        <div className="onboarding__brand">
          <span className="onboarding__logo">{t('title')}</span>
          <span className="muted">{t('header.duration')}</span>
        </div>
        <Button
          variant="ghost"
          loading={finishing === 'explore'}
          disabled={finishing !== null}
          onClick={() => finish('explore')}
        >
          {t('header.explore')}
        </Button>
      </header>

      <div className="onboarding__progress">
        <ProgressBar
          value={state.step}
          max={STEP_COUNT}
          label={t('header.progress')}
        />
        <p className="onboarding__step-count">
          {t('header.step', {step: state.step, total: STEP_COUNT})}
        </p>
        <ol className="onboarding__steps">
          {STEPS.map((step) => (
            <li
              key={step}
              className={
                step === state.step
                  ? 'is-current'
                  : step < state.step
                    ? 'is-done'
                    : undefined
              }
              aria-current={step === state.step ? 'step' : undefined}
            >
              {t(`steps.${step}`)}
            </li>
          ))}
        </ol>
      </div>

      {finishFailed ? (
        <p className="onboarding__alert" role="alert">
          {t('result.finishFailed')}
        </p>
      ) : null}
      {!canWrite ? (
        <p className="onboarding__notice" role="note">
          {t('readOnly')}
        </p>
      ) : null}

      <main className="onboarding__body">
        {state.step === 1 ? <GoalStep onboarding={onboarding} /> : null}
        {state.step === 2 ? <WorkspaceStep onboarding={onboarding} /> : null}
        {state.step === 3 ? <TemplateStep onboarding={onboarding} /> : null}
        {state.step === 4 ? <BuilderStep onboarding={onboarding} /> : null}
        {state.step === 5 ? <PublishStep onboarding={onboarding} /> : null}
        {state.step === 6 ? <TestStep onboarding={onboarding} /> : null}
        {state.step === 7 ? <ResultStep onboarding={onboarding} /> : null}
      </main>
    </div>
  );
}
