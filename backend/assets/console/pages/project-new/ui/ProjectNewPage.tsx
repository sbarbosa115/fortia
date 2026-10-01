import {useDocumentTitle} from '@shared/lib';
import {Icon, Tooltip} from '@shared/ui';
import {useEffect, useRef} from 'react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {useProjectWizard} from '../model/useProjectWizard';
import {STEP_KEYS, STEPS, type Step} from '../model/wizard';
import {OrganizationStep} from './OrganizationStep';
import {ProjectStep} from './ProjectStep';
import {QuestionsStep} from './QuestionsStep';
import {WizardStepper} from './WizardStepper';
import {WizardSummary} from './WizardSummary';
import './project-new.css';

/**
 * /projects/new (PRD §10.12): a follow-up project in three steps — questions, organization, project — with "What
 * we're going to create" beside them, as the admin console's wizard: a header with the crumbs, the steps and the
 * actions, then the current step and the summary. Nothing is saved until Create (except the questionnaire the
 * chat's approval saves).
 */
export function ProjectNewPage() {
  const {t} = useTranslation('pages.project-new');
  const {t: tShared} = useTranslation('shared');
  useDocumentTitle(`Mappi - ${t('title')}`);
  const wizard = useProjectWizard();
  const {step, errors} = wizard;
  const missing = STEPS.filter((index) => errors[index] !== null);
  const current = errors[step] ?? null;
  // With the questionnaire saved the chat is over: the next thing to press is Continue.
  const continueRef = useRef<HTMLButtonElement>(null);
  useEffect(() => {
    if (wizard.approved) {
      continueRef.current?.focus();
    }
  }, [wizard.approved]);

  const createButton = (
    <button
      type="button"
      className="prj-new__btn prj-new__btn--primary"
      onClick={wizard.submit}
      disabled={Boolean(wizard.createReason) || wizard.creating}
      aria-busy={wizard.creating || undefined}
    >
      <span className={wizard.creating ? 'prj-new__spin' : undefined}>
        <Icon name={wizard.creating ? 'loader' : 'plus'} size={16} />
      </span>
      {wizard.creating ? t('actions.creating') : t('actions.create')}
    </button>
  );

  return (
    <div className="prj-new">
      <header className="prj-new__header">
        <div className="prj-new__heading">
          <nav aria-label="Breadcrumb" className="prj-new__crumbs">
            <span className="prj-new__crumb">{t('workspace')}</span>
            <span aria-hidden="true">/</span>
            <Link
              to="/projects"
              className="prj-new__crumb prj-new__crumb--link"
            >
              {t('projects')}
            </Link>
            <span aria-hidden="true">/</span>
            <span className="prj-new__crumb" aria-current="page">
              {t('breadcrumb')}
            </span>
          </nav>
          <span className="prj-new__draft-badge">
            <span className="prj-new__draft-dot" aria-hidden="true" />
            {t('draftBadge')}
          </span>
        </div>

        <WizardStepper
          step={step}
          errors={errors}
          onGoTo={(index: Step) => wizard.goTo(index)}
        />

        <div className="prj-new__actions">
          <button
            type="button"
            className="prj-new__btn prj-new__btn--ghost"
            onClick={wizard.back}
            disabled={wizard.creating}
          >
            <Icon name="arrow-left" size={16} />
            {step === 0 ? tShared('actions.cancel') : t('actions.back')}
          </button>
          {step === 2 ? (
            wizard.createReason ? (
              <Tooltip content={wizard.createReason}>{createButton}</Tooltip>
            ) : (
              createButton
            )
          ) : (
            <button
              ref={continueRef}
              type="button"
              className="prj-new__btn prj-new__btn--primary"
              onClick={wizard.next}
              disabled={!wizard.canContinue}
            >
              {t('actions.continue')}
              <Icon name="arrow-right" size={16} />
            </button>
          )}
        </div>
      </header>

      <div className="prj-new__layout">
        <div className="prj-new__main">
          <div className="prj-new__hero">
            <p className="prj-new__kicker">
              {t('kicker', {number: step + 1, total: 3})}
            </p>
            <h1 className="prj-new__title">{t(`${STEP_KEYS[step]}.hero`)}</h1>
            <p className="prj-new__subtitle">
              {t(`${STEP_KEYS[step]}.subtitle`)}
            </p>
          </div>

          {wizard.showMissing && missing.length > 0 ? (
            <div className="prj-new__missing" role="alert">
              <p className="prj-new__missing-title">{t('missing.title')}</p>
              <ul>
                {missing.map((index) => (
                  <li key={index}>
                    <span>
                      {t(`steps.${STEP_KEYS[index]}`)}:{' '}
                      {t(`errors.${errors[index]}`)}
                    </span>
                    <button
                      type="button"
                      className="prj-new__link-button"
                      onClick={() => wizard.goTo(index as Step)}
                    >
                      {t('missing.goTo', {number: index + 1})}
                      <Icon name="chevron-right" size={14} />
                    </button>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          {step === 0 ? <QuestionsStep wizard={wizard} /> : null}
          {step === 1 ? <OrganizationStep wizard={wizard} /> : null}
          {step === 2 ? <ProjectStep wizard={wizard} /> : null}

          {current !== null ? (
            <p className="prj-new__hint">
              <Icon name="info" size={14} />
              {t(`errors.${current}`)}
            </p>
          ) : null}
        </div>

        <div className="prj-new__aside">
          <WizardSummary wizard={wizard} />
        </div>
      </div>
    </div>
  );
}
