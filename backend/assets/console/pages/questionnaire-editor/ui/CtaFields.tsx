import {Field, TextInput} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';
import type {DraftCta} from '../model/types';

/** The call to action: title (≤ 120), description (≤ 200), button text (≤ 50) and an http(s) URL. */
export function CtaFields() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const {cta} = editor.draft;
  const issues = editor.issuesOf(3);
  const error = (field: string) => {
    const issue = issues.find((i) => i.field === `cta.${field}`);
    return issue ? t(issue.key) : null;
  };
  const set = (patch: Partial<DraftCta>) =>
    editor.update({cta: {...cta, ...patch}});
  return (
    <div className="stack">
      <Field label={t('cta.title')} required error={error('title')}>
        <TextInput
          value={cta.title}
          onChange={(e) => set({title: e.target.value})}
        />
      </Field>
      <Field label={t('cta.description')} error={error('description')}>
        <TextInput
          value={cta.description}
          onChange={(e) => set({description: e.target.value})}
        />
      </Field>
      <div className="grid-2">
        <Field label={t('cta.buttonText')} required error={error('buttonText')}>
          <TextInput
            value={cta.buttonText}
            onChange={(e) => set({buttonText: e.target.value})}
          />
        </Field>
        <Field label={t('cta.url')} required error={error('url')}>
          <TextInput
            type="url"
            placeholder={t('cta.urlPlaceholder')}
            value={cta.url}
            onChange={(e) => set({url: e.target.value})}
          />
        </Field>
      </div>
    </div>
  );
}
