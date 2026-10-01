import type {
  AnswerValue,
  Control,
  Gender,
  Question,
} from '@respondent/entities/session';

/** What every answer control receives (PRD §9.4). */
export type ControlProps = {
  question: Question;
  control: Control;
  gender: Gender;
  /** Locked (an approved answer in a retry): shown, never written (§9.4). */
  disabled: boolean;
  onChange: (value: AnswerValue) => void;
  /** Writes one named control: the themes with several fields (§9.5). */
  onChangeControl: (controlName: string, value: AnswerValue) => void;
  /** Enter in a text answer submits (§9.4 text). */
  onSubmit?: () => void;
};
