import {joinClasses, useDocumentTitle} from '@shared/lib';
import {Button, Icon, PageHeader} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {useProjectWizard} from '../model/useProjectWizard';
import {isReachable, STEP_KEYS, STEPS, type Step} from '../model/wizard';
import {OrganizationStep} from './OrganizationStep';
import {ProjectStep} from './ProjectStep';
import {QuestionsStep} from './QuestionsStep';
import {WizardSummary} from './WizardSummary';
import './project-new.css';

/**
 * /projects/new (PRD §10.12): a follow-up project in three steps — questions, organization, project — with "What
 * we're going to create" beside them. Nothing is saved until Create (except the questionnaire the chat's approval
 * saves).
 */
export function ProjectNewPage() {
  const {t} = useTranslation('pages.project-new');
  const {t: tShared} = useTranslation('shared');
  useDocumentTitle(`Mappi - ${t('title')}`);
  const wizard = useProjectWizard();
  const {step, errors} = wizard;
  const missing = STEPS.filter((index) => errors[index] !== null);

  return (
    <div className="prj-new">
      <PageHeader
        title={t('title')}
        subtitle={
          <>
            <Link to="/projects">{t('breadcrumb')}</Link> · {t('subtitle')}
          </>
        }
      />

      <ol className="prj-new__steps" aria-label={t('steps.label')}>
        {STEPS.map((index) => {
          const state =
            index === step
              ? 'current'
              : index < step
                ? errors[index] === null
                  ? 'done'
                  : 'incomplete'
                : 'upcoming';
          return (
            <li key={index}>
              <button
                type="button"
                className={joinClasses(
                  'prj-new__step',
                  `prj-new__step--${state}`,
                )}
                aria-current={index === step ? 'step' : undefined}
                disabled={!isReachable(errors, index)}
                onClick={() => wizard.goTo(index)}
              >
                <span className="prj-new__step-number" aria-hidden>
                  {state === 'done' ? (
                    <Icon name="check" size={14} />
                  ) : state === 'incomplete' ? (
                    '!'
                  ) : (
                    index + 1
                  )}
                </span>
                {t(`steps.${STEP_KEYS[index]}`)}
                {state === 'done' || state === 'incomplete' ? (
                  <span className="visually-hidden">
                    {' '}
                    ({t(`steps.${state}`)})
                  </span>
                ) : null}
              </button>
            </li>
          );
        })}
      </ol>

      <div className="prj-new__layout">
        <div className="prj-new__main">
          <div className="prj-new__hero">
            <p className="prj-new__kicker">
              {t('kicker', {number: step + 1, total: 3})}
            </p>
            <h2 className="serif-heading">{t(`${STEP_KEYS[step]}.hero`)}</h2>
            <p className="muted">{t(`${STEP_KEYS[step]}.subtitle`)}</p>
          </div>

          {wizard.showMissing && missing.length > 0 ? (
            <div className="prj-new__missing" role="alert">
              <p>{t('missing.title')}</p>
              <ul>
                {missing.map((index) => (
                  <li key={index}>
                    <span>
                      {t(`steps.${STEP_KEYS[index]}`)}:{' '}
                      {t(`errors.${errors[index]}`)}
                    </span>
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => wizard.goTo(index as Step)}
                      disabled={!isReachable(errors, index)}
                    >
                      {t('missing.goTo', {number: index + 1})}
                    </Button>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          {step === 0 ? <QuestionsStep wizard={wizard} /> : null}
          {step === 1 ? <OrganizationStep wizard={wizard} /> : null}
          {step === 2 ? <ProjectStep wizard={wizard} /> : null}

          {errors[step] !== null ? (
            <p className="prj-new__hint">
              <Icon name="info" size={14} />
              {t(`errors.${errors[step]}`)}
            </p>
          ) : null}

          <div className="prj-new__actions">
            <Button
              icon={<Icon name="chevron-left" size={16} />}
              onClick={wizard.back}
              disabled={wizard.creating}
            >
              {step === 0 ? tShared('actions.cancel') : t('actions.back')}
            </Button>
            {step < 2 ? (
              <Button
                variant="primary"
                onClick={wizard.next}
                disabled={!wizard.canContinue}
              >
                {t('actions.continue')}
                <Icon name="chevron-right" size={16} />
              </Button>
            ) : (
              <Button
                variant="primary"
                icon={<Icon name="plus" size={16} />}
                loading={wizard.creating}
                disabledReason={wizard.createReason}
                onClick={wizard.submit}
              >
                {wizard.creating ? t('actions.creating') : t('actions.create')}
              </Button>
            )}
          </div>
        </div>

        <WizardSummary wizard={wizard} />
      </div>
    </div>
  );
}
