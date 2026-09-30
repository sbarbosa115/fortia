import {Tooltip} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';
import type {Step} from '../model/validate';

const STEPS: Step[] = [1, 2, 3];

/** Details → Questions → the type's last step. A step opens only when the ones before it are valid. */
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
        const reachable = editor.canReach(step);
        const button = (
          <button
            type="button"
            className="stepper__step"
            aria-current={editor.step === step ? 'step' : undefined}
            disabled={!reachable}
            onClick={() => editor.goTo(step)}
          >
            <span className="stepper__number" aria-hidden>
              {step}
            </span>
            <span>{labels[step]}</span>
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
  );
}
