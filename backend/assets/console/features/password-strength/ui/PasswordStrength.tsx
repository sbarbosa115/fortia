import {useTranslation} from 'react-i18next';
import {passwordScore, strengthLevel} from '../lib/score';
import './password-strength.css';

/** 1–4 bars with the level's name next to them (colour is never the only cue, PRD §14.5). */
export function PasswordStrength({password}: {password: string}) {
  const {t} = useTranslation('features.password-strength');
  if (!password) {
    return null;
  }
  const score = passwordScore(password);
  const level = strengthLevel(score);
  return (
    <div
      className={`strength strength--${level}`}
      aria-live="polite"
      data-score={score}
    >
      <div className="strength__bars" aria-hidden>
        {[1, 2, 3, 4].map((bar) => (
          <span
            key={bar}
            className={
              bar <= score ? 'strength__bar strength__bar--on' : 'strength__bar'
            }
          />
        ))}
      </div>
      <span className="strength__label">
        {t('label', {level: t(`levels.${level}`)})}
      </span>
    </div>
  );
}
