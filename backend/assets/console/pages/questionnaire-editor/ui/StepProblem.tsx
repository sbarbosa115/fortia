import {Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';

/** What still blocks the step, said in words next to where it can be fixed, with "Show question" to jump to it. */
export function StepProblem({
  message,
  onShow,
}: {
  message: string;
  onShow?: () => void;
}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  return (
    <div role="status" className="step-problem">
      <Icon name="alert-circle" size={16} />
      <span className="step-problem__text">{message}</span>
      {onShow ? (
        <button type="button" className="step-problem__show" onClick={onShow}>
          {t('questions.showProblem')}
        </button>
      ) : null}
    </div>
  );
}
