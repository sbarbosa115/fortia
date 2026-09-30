import type {ReactNode} from 'react';

export type Choice<V extends string> = {
  value: V;
  title: ReactNode;
  body?: ReactNode;
  extra?: ReactNode;
  disabled?: boolean;
};

/** A set of selectable cards (role="radiogroup"): the type picker, the role picker, the goal picker… */
export function ChoiceCards<V extends string>({
  choices,
  value,
  onChange,
  label,
}: {
  choices: Choice<V>[];
  value: V | null;
  onChange: (value: V) => void;
  label: string;
}) {
  return (
    <div className="choice-cards" role="radiogroup" aria-label={label}>
      {choices.map((choice) => (
        <button
          key={choice.value}
          type="button"
          role="radio"
          aria-checked={choice.value === value}
          className="choice-card"
          disabled={choice.disabled}
          onClick={() => onChange(choice.value)}
        >
          <span className="choice-card__title">{choice.title}</span>
          {choice.body ? (
            <span className="choice-card__body">{choice.body}</span>
          ) : null}
          {choice.extra}
        </button>
      ))}
    </div>
  );
}
