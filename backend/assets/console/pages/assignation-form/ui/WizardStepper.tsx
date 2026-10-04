import {joinClasses} from '@shared/lib';
import {Icon} from '@shared/ui';
import {Fragment} from 'react';
import {useTranslation} from 'react-i18next';
import {isReachable, STEP_KEYS, STEPS, type Step} from '../model/wizard';

type StepState = 'done' | 'current' | 'incomplete' | 'upcoming';

/** The three steps of the wizard; a step opens once the ones before it are complete, going back is always allowed. */
export function WizardStepper({
  step,
  errors,
  onGoTo,
}: {
  step: Step;
  errors: (string | null)[];
  onGoTo: (step: Step) => void;
}) {
  const {t} = useTranslation('pages.assignation-form');

  return (
    <ol className="asg-wiz__steps" aria-label={t('steps.label')}>
      {STEPS.map((index) => {
        const error = errors[index] ?? null;
        const state: StepState =
          index === step
            ? 'current'
            : index < step
              ? error === null
                ? 'done'
                : 'incomplete'
              : 'upcoming';
        const reachable = isReachable(errors, index);
        return (
          <Fragment key={index}>
            <li>
              <button
                type="button"
                className={joinClasses(
                  'asg-wiz__step',
                  `asg-wiz__step--${state}`,
                )}
                aria-current={state === 'current' ? 'step' : undefined}
                title={
                  state === 'incomplete' && error
                    ? t(`errors.${error}`)
                    : undefined
                }
                disabled={!reachable}
                onClick={() => onGoTo(index)}
              >
                <span className="asg-wiz__step-number" aria-hidden="true">
                  {state === 'done' ? (
                    <Icon name="check" size={14} />
                  ) : state === 'incomplete' ? (
                    '!'
                  ) : (
                    index + 1
                  )}
                </span>
                <span>{t(`steps.${STEP_KEYS[index]}`)}</span>
                {state === 'done' || state === 'incomplete' ? (
                  <span className="visually-hidden">
                    {' '}
                    {t(`steps.${state}`)}
                  </span>
                ) : null}
              </button>
            </li>
            {index < STEPS.length - 1 ? (
              <li
                aria-hidden="true"
                className={joinClasses(
                  'asg-wiz__step-line',
                  state === 'done' && 'asg-wiz__step-line--done',
                )}
              />
            ) : null}
          </Fragment>
        );
      })}
    </ol>
  );
}
