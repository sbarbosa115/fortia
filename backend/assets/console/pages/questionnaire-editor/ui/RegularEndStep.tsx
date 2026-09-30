import {Card, CardBody, Field, TextArea, TextInput, Toggle} from '@shared/ui';
import {type ReactNode, useId} from 'react';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';
import {TEXT_LIMIT} from '../model/types';
import {CtaFields} from './CtaFields';

/** Step 3 of a Regular questionnaire, "When it ends" (PRD §10.5): thank-you message, call to action, capture data. */
export function RegularEndStep() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const headingId = useId();
  const {draft, update} = useEditorContext();
  return (
    <section className="editor__step" aria-labelledby={headingId}>
      <h2 id={headingId} className="editor__step-title">
        {t('end.title')}
      </h2>
      <p className="muted">{t('end.subtitle')}</p>
      <div className="stack">
        <EndBlock
          label={t('end.thankYou')}
          checked={draft.thankYouOn}
          onChange={(thankYouOn) => update({thankYouOn})}
        >
          <Field label={t('end.thankYouTitle')}>
            <TextInput
              maxLength={TEXT_LIMIT}
              value={draft.thankYouTitle}
              onChange={(e) => update({thankYouTitle: e.target.value})}
            />
          </Field>
          <Field label={t('end.thankYouMessage')}>
            <TextArea
              maxLength={TEXT_LIMIT}
              value={draft.thankYouMessage}
              onChange={(e) => update({thankYouMessage: e.target.value})}
            />
          </Field>
        </EndBlock>
        <EndBlock
          label={t('end.cta')}
          hint={t('end.ctaHint')}
          checked={draft.ctaOn}
          onChange={(ctaOn) => update({ctaOn})}
        >
          <CtaFields />
        </EndBlock>
        <EndBlock
          label={t('end.capture')}
          hint={t('end.captureHint')}
          checked={draft.captureUserData}
          onChange={(captureUserData) => update({captureUserData})}
        />
      </div>
    </section>
  );
}

/** A block of the ending: a switch, and its fields while it is on. */
export function EndBlock({
  label,
  hint,
  checked,
  onChange,
  children,
}: {
  label: string;
  hint?: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  children?: ReactNode;
}) {
  return (
    <Card>
      <CardBody>
        <div className="stack">
          <div className="editor__toggle-row">
            <Toggle checked={checked} label={label} onChange={onChange} />
            {hint ? <span className="muted">{hint}</span> : null}
          </div>
          {checked && children ? children : null}
        </div>
      </CardBody>
    </Card>
  );
}
