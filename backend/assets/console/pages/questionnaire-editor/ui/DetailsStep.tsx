import {publicFlowUrl} from '@shared/config';
import {slugify} from '@shared/lib';
import {Card, CardBody, Field, TextArea, TextInput, Toggle} from '@shared/ui';
import {useId} from 'react';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';

/** Step 1 (PRD §10.5): title, custom link (slug), description, landing page, disclaimer. */
export function DetailsStep() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const headingId = useId();
  const {draft, update, issuesOf, slugInUse} = useEditorContext();
  const slugIssue = issuesOf(1).find((issue) => issue.field === 'slug');
  const slugError =
    slugInUse !== null && slugInUse === draft.slug
      ? t('errors.slugInUse')
      : slugIssue
        ? t(slugIssue.key)
        : null;
  const previewSlug = draft.slug.trim() || slugify(draft.title) || '…';
  return (
    <section className="editor__step" aria-labelledby={headingId}>
      <h2 id={headingId} className="editor__step-title">
        {t('details.title')}
      </h2>
      <p className="muted">{t('details.subtitle')}</p>
      <Card>
        <CardBody>
          <div className="stack">
            <Field label={t('details.titleLabel')} required>
              <TextInput
                value={draft.title}
                placeholder={t('details.titlePlaceholder')}
                maxLength={200}
                onChange={(e) => update({title: e.target.value})}
              />
            </Field>
            <Field
              label={t('details.slugLabel')}
              hint={
                <>
                  {t('details.slugHint')}{' '}
                  <span className="editor__slug-preview">
                    {t('details.slugPreview', {url: publicFlowUrl(previewSlug)})}
                  </span>
                </>
              }
              error={slugError}
            >
              <TextInput
                value={draft.slug}
                maxLength={100}
                spellCheck={false}
                autoCapitalize="none"
                onChange={(e) => update({slug: e.target.value})}
              />
            </Field>
            <Field label={t('details.descriptionLabel')}>
              <TextArea
                value={draft.description}
                onChange={(e) => update({description: e.target.value})}
              />
            </Field>
            <div className="editor__toggle-row">
              <Toggle
                checked={draft.landingPage}
                label={t('details.landingLabel')}
                onChange={(landingPage) => update({landingPage})}
              />
              <span className="muted">{t('details.landingHint')}</span>
            </div>
            <div className="editor__toggle-row">
              <Toggle
                checked={draft.disclaimerOn}
                label={t('details.disclaimerLabel')}
                onChange={(disclaimerOn) => update({disclaimerOn})}
              />
              <span className="muted">{t('details.disclaimerHint')}</span>
            </div>
            {draft.disclaimerOn ? (
              <Field label={t('details.disclaimerText')} required>
                <TextArea
                  value={draft.disclaimer}
                  onChange={(e) => update({disclaimer: e.target.value})}
                />
              </Field>
            ) : null}
          </div>
        </CardBody>
      </Card>
    </section>
  );
}
