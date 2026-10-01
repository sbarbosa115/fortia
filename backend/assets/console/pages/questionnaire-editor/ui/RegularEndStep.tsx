import {
  Field,
  Icon,
  type IconName,
  IconButton,
  TextArea,
  TextInput,
  Toggle,
} from '@shared/ui';
import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';
import {type Draft, TEXT_LIMIT} from '../model/types';
import {CtaFields} from './CtaFields';

/** The elements a Regular questionnaire's end can show, in the order the respondent sees them. */
export type EndElement = 'thank_you' | 'cta' | 'capture';
export const END_ELEMENTS: EndElement[] = ['thank_you', 'cta', 'capture'];

export const END_ELEMENT_ICON: Record<EndElement, IconName> = {
  thank_you: 'heart',
  cta: 'mouse-pointer',
  capture: 'user-round',
};

const FLAG: Record<EndElement, 'thankYouOn' | 'ctaOn' | 'captureUserData'> = {
  thank_you: 'thankYouOn',
  cta: 'ctaOn',
  capture: 'captureUserData',
};

/** Which elements are on the end page. */
export function endElementsOf(draft: Draft): EndElement[] {
  return END_ELEMENTS.filter((element) => draft[FLAG[element]]);
}

export function endElementPatch(
  element: EndElement,
  on: boolean,
): Partial<Draft> {
  return {[FLAG[element]]: on};
}

/** Adds or removes an element; the preview follows the contact form as it comes and goes. */
function useToggleElement() {
  const {update, setPreviewTab} = useEditorContext();
  return (element: EndElement, on: boolean) => {
    update(endElementPatch(element, on));
    if (element === 'capture') {
      setPreviewTab(on ? 'contact' : 'final');
    }
  };
}

/**
 * Step 3 of a Regular questionnaire, "When it ends" (PRD §10.5), as in the admin console: one card per element on
 * the end page (thank-you message, call to action, capture data), each removable; the pool on the left adds them.
 */
export function RegularEndStep() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const {draft, update} = useEditorContext();
  const toggle = useToggleElement();
  const blocks = endElementsOf(draft);

  const body = (element: EndElement): ReactNode => {
    if (element === 'thank_you') {
      return (
        <div className="stack">
          <Field label={t('end.thankYouTitle')}>
            <TextInput
              maxLength={TEXT_LIMIT}
              value={draft.thankYouTitle}
              placeholder={t('shopper.defaultEndTitle')}
              onChange={(e) => update({thankYouTitle: e.target.value})}
            />
          </Field>
          <Field label={t('end.thankYouMessage')}>
            <TextArea
              rows={3}
              maxLength={TEXT_LIMIT}
              value={draft.thankYouMessage}
              placeholder={t('shopper.defaultEndMessage')}
              onChange={(e) => update({thankYouMessage: e.target.value})}
            />
          </Field>
        </div>
      );
    }
    if (element === 'cta') {
      return <CtaFields />;
    }
    return <CaptureNote />;
  };

  return (
    <div className="blocks">
      {blocks.length === 0 ? (
        <p className="blocks__empty">{t('end.empty')}</p>
      ) : null}
      {blocks.map((element) => {
        const name = t(`end.elements.${element}.name`);
        return (
          <section
            key={element}
            className="ccard block"
            role="group"
            aria-label={name}
          >
            <div className="block__head">
              <span className="block__icon" aria-hidden>
                <Icon name={END_ELEMENT_ICON[element]} size={16} />
              </span>
              <div className="block__text">
                <h3>{name}</h3>
                <p>{t(`end.elements.${element}.description`)}</p>
              </div>
              <IconButton
                size="sm"
                label={t('end.remove', {name})}
                icon={<Icon name="close" size={16} />}
                onClick={() => toggle(element, false)}
              />
            </div>
            <div className="block__body">{body(element)}</div>
          </section>
        );
      })}
    </div>
  );
}

/** Body of "Capture data": what the contact form asks for. */
function CaptureNote() {
  const {t} = useTranslation('pages.questionnaire-editor');
  return (
    <div className="capture-note">
      {t('end.elements.capture.note')}
      {(['name', 'email', 'phone'] as const).map((field) => (
        <span key={field} className="capture-note__chip">
          {t(`shopper.contactFields.${field}`)}
        </span>
      ))}
    </div>
  );
}

/** The "Elements" panel on the left of the last step: click one to add it to the end page. */
export function ElementPool() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const {draft} = useEditorContext();
  const toggle = useToggleElement();
  const used = endElementsOf(draft);
  return (
    <div className="outline">
      <div className="outline__head outline__head--stacked">
        <h2>{t('end.poolTitle')}</h2>
        <p>{t('end.poolHelp')}</p>
      </div>
      <ul className="pool">
        {END_ELEMENTS.map((element) => {
          const isUsed = used.includes(element);
          const name = t(`end.elements.${element}.name`);
          return (
            <li key={element}>
              <button
                type="button"
                className="pool__item"
                disabled={isUsed}
                aria-label={isUsed ? t('end.used', {name}) : undefined}
                onClick={() => toggle(element, true)}
              >
                <span className="block__icon" aria-hidden>
                  <Icon name={END_ELEMENT_ICON[element]} size={16} />
                </span>
                <span className="pool__text">
                  <span className="pool__name">{name}</span>
                  <span className="pool__description">
                    {t(`end.elements.${element}.description`)}
                  </span>
                </span>
                <span
                  className={
                    isUsed ? 'pool__mark pool__mark--used' : 'pool__mark'
                  }
                  aria-hidden
                >
                  <Icon name={isUsed ? 'check' : 'plus'} size={16} />
                </span>
              </button>
            </li>
          );
        })}
      </ul>
    </div>
  );
}

/** A block of the generic editor's ending: a switch, and its fields while it is on. */
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
    <section className="ccard">
      <div className="stack">
        <div className="editor__toggle-row">
          <Toggle checked={checked} label={label} onChange={onChange} />
          {hint ? <span className="muted">{hint}</span> : null}
        </div>
        {checked && children ? children : null}
      </div>
    </section>
  );
}
