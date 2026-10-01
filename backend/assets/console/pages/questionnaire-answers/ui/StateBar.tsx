import {STATES, stateOf, stateStep} from '@console/entities/answer';
import {useTranslation} from 'react-i18next';

/** The 4-segment state of a session, with its name as text (never colour alone, PRD §14.5). */
export function StateBar({status}: {status: string}) {
  const {t} = useTranslation('pages.questionnaire-answers');
  const step = stateStep(status);
  const label = t(`states.${stateOf(status)}`);
  return (
    <span className="answers-state" title={label}>
      <span
        className="answers-state__bar"
        role="img"
        aria-label={t('stateOf', {state: label, step, total: STATES.length})}
      >
        {STATES.map((state, i) => (
          <span
            key={state}
            className={`answers-state__segment${i < step ? ' answers-state__segment--on' : ''}`}
          />
        ))}
      </span>
      <span className="answers-state__label">{label}</span>
    </span>
  );
}
