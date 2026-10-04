import {useDocumentTitle} from '@shared/lib';
import {Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {useAssignationWizard} from '../model/useAssignationWizard';
import {STEP_KEYS, STEPS, type Step} from '../model/wizard';
import {DetailsStep} from './DetailsStep';
import {OrganizationStep} from './OrganizationStep';
import {QuestionnairesStep} from './QuestionnairesStep';
import {WizardStepper} from './WizardStepper';
import {WizardSummary} from './WizardSummary';
import './assignation-form.css';

/**
 * /assignations/new: an assignation in three steps — the questionnaires (any number, searched and filtered), the
 * organization (listed or created here), then its name and deadline — with "What we're going to create" beside them.
 * Each questionnaire becomes a follow-up of the organization for everybody in it. Nothing is saved until Create.
 */
export function AssignationFormPage() {
  const {t} = useTranslation('pages.assignation-form');
  const {t: tShared} = useTranslation('shared');
  useDocumentTitle(`Mappi - ${t('title')}`);
  const wizard = useAssignationWizard();
  const {step, errors} = wizard;
  const missing = STEPS.filter((index) => errors[index] !== null);
  const current = errors[step] ?? null;

  return (
    <div className="asg-wiz">
      <header className="asg-wiz__header">
        <div className="asg-wiz__heading">
          <nav aria-label="Breadcrumb" className="asg-wiz__crumbs">
            <span className="asg-wiz__crumb">{t('workspace')}</span>
            <span aria-hidden="true">/</span>
            <Link
              to="/assignations"
              className="asg-wiz__crumb asg-wiz__crumb--link"
            >
              {t('assignations')}
            </Link>
            <span aria-hidden="true">/</span>
            <span className="asg-wiz__crumb" aria-current="page">
              {t('breadcrumb')}
            </span>
          </nav>
          <span className="asg-wiz__draft-badge">
            <span className="asg-wiz__draft-dot" aria-hidden="true" />
            {t('draftBadge')}
          </span>
        </div>

        <WizardStepper
          step={step}
          errors={errors}
          onGoTo={(index: Step) => wizard.goTo(index)}
        />

        <div className="asg-wiz__actions">
          <button
            type="button"
            className="asg-wiz__btn asg-wiz__btn--ghost"
            onClick={wizard.back}
            disabled={wizard.creating}
          >
            <Icon name="arrow-left" size={16} />
            {step === 0 ? tShared('actions.cancel') : t('actions.back')}
          </button>
          {step === 2 ? (
            <button
              type="button"
              className="asg-wiz__btn asg-wiz__btn--primary"
              onClick={wizard.submit}
              disabled={wizard.creating}
              aria-busy={wizard.creating || undefined}
            >
              <span className={wizard.creating ? 'asg-wiz__spin' : undefined}>
                <Icon name={wizard.creating ? 'loader' : 'plus'} size={16} />
              </span>
              {wizard.creating ? t('actions.creating') : t('actions.create')}
            </button>
          ) : (
            <button
              type="button"
              className="asg-wiz__btn asg-wiz__btn--primary"
              onClick={wizard.next}
              disabled={!wizard.canContinue}
            >
              {t('actions.continue')}
              <Icon name="arrow-right" size={16} />
            </button>
          )}
        </div>
      </header>

      <div className="asg-wiz__layout">
        <div className="asg-wiz__main">
          <div className="asg-wiz__hero">
            <p className="asg-wiz__kicker">
              {t('kicker', {number: step + 1, total: STEPS.length})}
            </p>
            <h1 className="asg-wiz__title">{t(`${STEP_KEYS[step]}.hero`)}</h1>
            <p className="asg-wiz__subtitle">
              {t(`${STEP_KEYS[step]}.subtitle`)}
            </p>
          </div>

          {wizard.showMissing && missing.length > 0 ? (
            <div className="asg-wiz__missing" role="alert">
              <p className="asg-wiz__missing-title">{t('missing.title')}</p>
              <ul>
                {missing.map((index) => (
                  <li key={index}>
                    <span>
                      {t(`steps.${STEP_KEYS[index]}`)}:{' '}
                      {t(`errors.${errors[index]}`)}
                    </span>
                    <button
                      type="button"
                      className="asg-wiz__link-button"
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

          {step === 0 ? <QuestionnairesStep wizard={wizard} /> : null}
          {step === 1 ? <OrganizationStep wizard={wizard} /> : null}
          {step === 2 ? <DetailsStep wizard={wizard} /> : null}

          {current !== null && !wizard.showMissing ? (
            <p className="asg-wiz__hint">
              <Icon name="info" size={14} />
              {t(`errors.${current}`)}
            </p>
          ) : null}
        </div>

        <div className="asg-wiz__aside">
          <WizardSummary wizard={wizard} />
        </div>
      </div>
    </div>
  );
}
