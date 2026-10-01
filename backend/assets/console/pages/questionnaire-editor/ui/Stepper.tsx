import {Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';
import type {Step} from '../model/validate';

const STEPS: Step[] = [1, 2, 3];

/**
 * Details → Questions → the type's last step, as in the admin console: done steps show a check, the current one a
 * ring. A reachable step other than the current one is a button; a step opens only when the ones before it are valid.
 */
export function Stepper() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const labels: Record<Step, string> = {
    1: t('steps.details'),
    2: t('steps.questions'),
    3: t(
      `steps.${editor.draft.kind === 'generic' ? 'regular' : editor.draft.kind}`,
    ),
  };
  return (
    <ol className="stepper" aria-label={t('steps.label')}>
      {STEPS.map((step) => {
        const done = step < editor.step;
        const active = step === editor.step;
        const clickable = editor.canReach(step) && !active;
        const state = done ? 'done' : active ? 'active' : 'todo';
        const content = (
          <>
            <span
              className={`stepper__number stepper__number--${state}`}
              aria-hidden
            >
              {done ? <Icon name="check" size={14} /> : step}
            </span>
            <span className={`stepper__label stepper__label--${state}`}>
              {labels[step]}
            </span>
          </>
        );
        return (
          <li
            key={step}
            className="stepper__item"
            aria-current={active ? 'step' : undefined}
          >
            {clickable ? (
              <button
                type="button"
                className="stepper__step"
                onClick={() => editor.goTo(step)}
              >
                {content}
              </button>
            ) : (
              <span className="stepper__step stepper__step--static">
                {content}
              </span>
            )}
            {step < 3 ? (
              <span
                className={
                  done ? 'stepper__line stepper__line--done' : 'stepper__line'
                }
                aria-hidden
              />
            ) : null}
          </li>
        );
      })}
    </ol>
  );
}
