import {Field, Icon, TextInput} from '@shared/ui';
import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';
import type {DraftCta} from '../model/types';

const LIMITS = {title: 120, description: 200, buttonText: 50};

/**
 * The call to action, as the admin console embeds it in a block: title (≤ 120), description (≤ 200), button text
 * (≤ 50) with their counters, and an http(s) URL. The live preview draws the banner.
 */
export function CtaFields() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const {cta} = editor.draft;
  const issues = editor.issuesOf(3);
  // As in the admin console, one problem at a time: the first one, on its field.
  const first = issues.find((i) => i.field?.startsWith('cta.'));
  const error = (field: string) =>
    first && first.field === `cta.${field}` ? t(first.key) : null;
  const set = (patch: Partial<DraftCta>) =>
    editor.update({cta: {...cta, ...patch}});
  const label = (text: string, value?: string, max?: number): ReactNode => (
    <>
      {text}
      {max !== undefined ? (
        <span className="cta-fields__counter" aria-hidden>
          {`${value?.length ?? 0}/${max}`}
        </span>
      ) : null}
    </>
  );

  return (
    <div className="cta-fields">
      <Field
        label={label(t('cta.title'), cta.title, LIMITS.title)}
        required
        error={error('title')}
      >
        <TextInput
          value={cta.title}
          placeholder={t('cta.titlePlaceholder')}
          onChange={(e) => set({title: e.target.value})}
        />
      </Field>
      <Field
        label={label(t('cta.description'), cta.description, LIMITS.description)}
        error={error('description')}
      >
        <TextInput
          value={cta.description}
          placeholder={t('cta.descriptionPlaceholder')}
          onChange={(e) => set({description: e.target.value})}
        />
      </Field>
      <Field
        label={label(t('cta.buttonText'), cta.buttonText, LIMITS.buttonText)}
        required
        error={error('buttonText')}
      >
        <TextInput
          value={cta.buttonText}
          placeholder={t('cta.buttonPlaceholder')}
          onChange={(e) => set({buttonText: e.target.value})}
        />
      </Field>
      <Field
        label={t('cta.url')}
        hint={t('cta.urlHint')}
        required
        error={error('url')}
      >
        <TextInput
          type="url"
          inputMode="url"
          placeholder={t('cta.urlPlaceholder')}
          value={cta.url}
          onChange={(e) => set({url: e.target.value})}
        />
      </Field>
      <p className="cta-fields__note">
        <Icon name="external" size={16} />
        {t('cta.newTab')}
      </p>
    </div>
  );
}
