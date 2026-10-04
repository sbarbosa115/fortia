import {useViewer} from '@console/entities/viewer';
import {publicFlowUrl} from '@shared/config';
import {slugify} from '@shared/lib';
import {Field, Icon, TextArea, TextInput} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';
import {SwitchRow} from './SwitchRow';
import {TagsField} from './TagsField';

/** The fixed part of the public link ("https://…/f/"), absolute even when the respondent app shares this origin. */
function linkPrefix(): string {
  return decodeURI(
    new URL(publicFlowUrl(''), window.location.origin).toString(),
  );
}

/**
 * Step 1 (PRD §10.5), as in the admin console: the questionnaire's details (title, custom link with its copy button,
 * description, tags), then "Before starting" (landing page, disclaimer).
 */
export function DetailsStep() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const {draft, update, issuesOf, slugInUse, setPreviewTab} =
    useEditorContext();
  const {canWrite} = useViewer();
  const [titleTouched, setTitleTouched] = useState(false);
  const [copied, setCopied] = useState(false);
  const slugIssue = issuesOf(1).find((issue) => issue.field === 'slug');
  const slugError =
    slugInUse !== null && slugInUse === draft.slug
      ? t('errors.slugInUse')
      : slugIssue
        ? t(slugIssue.key)
        : null;
  const prefix = linkPrefix();
  const effectiveSlug = slugify(draft.slug.trim() || draft.title);
  const fullUrl = `${prefix}${effectiveSlug}`;
  const disclaimerMissing =
    draft.disclaimerOn && draft.disclaimer.trim() === '';

  const copy = () => {
    void navigator.clipboard?.writeText(fullUrl).then(() => {
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1500);
    });
  };

  return (
    <div className="details">
      <section className="ccard" aria-labelledby="details-card">
        <h2 id="details-card" className="ccard__title">
          {t('details.cardTitle')}
        </h2>
        <Field
          label={t('details.titleLabel')}
          required
          error={
            titleTouched && draft.title.trim() === ''
              ? t('details.titleRequired')
              : null
          }
        >
          <TextInput
            id="creation-title"
            maxLength={200}
            value={draft.title}
            placeholder={t('details.titlePlaceholder')}
            onBlur={() => setTitleTouched(true)}
            onChange={(e) => update({title: e.target.value})}
          />
        </Field>
        <div className="field">
          <label className="field__label" htmlFor="creation-slug">
            {t('details.slugLabel')}
          </label>
          <div
            className={
              slugError ? 'slug-field slug-field--invalid' : 'slug-field'
            }
          >
            <span className="slug-field__prefix">{prefix}</span>
            <input
              id="creation-slug"
              className="slug-field__input"
              maxLength={100}
              spellCheck={false}
              autoCapitalize="none"
              value={draft.slug}
              placeholder={effectiveSlug}
              aria-invalid={slugError ? true : undefined}
              aria-describedby="creation-slug-hint"
              onChange={(e) =>
                update({
                  slug: e.target.value
                    .toLowerCase()
                    .replace(/[^a-z0-9-]+/g, '-'),
                })
              }
            />
            <button
              type="button"
              className="slug-field__copy"
              aria-label={copied ? t('details.copied') : t('details.copyLink')}
              title={copied ? t('details.copied') : t('details.copyLink')}
              onClick={copy}
            >
              <Icon name={copied ? 'check' : 'copy'} size={16} />
            </button>
          </div>
          {slugError ? (
            <span className="field__error" role="alert">
              {slugError}
            </span>
          ) : null}
          <span id="creation-slug-hint" className="field__hint">
            {t('details.slugPreviewLabel')}{' '}
            <strong className="slug-field__url">{fullUrl}</strong>
            {'. '}
            {t('details.slugHint')}
          </span>
        </div>
        <Field label={t('details.descriptionLabel')}>
          <TextArea
            rows={3}
            value={draft.description}
            placeholder={t('details.descriptionPlaceholder')}
            onChange={(e) => update({description: e.target.value})}
          />
        </Field>
        <TagsField
          tags={draft.tags}
          onChange={(tags) => update({tags})}
          disabledReason={
            canWrite ? null : t('readOnly.change', {ns: 'shared'})
          }
        />
      </section>

      <section className="ccard" aria-labelledby="details-before">
        <h2 id="details-before" className="ccard__title">
          {t('details.beforeStart')}
        </h2>
        <SwitchRow
          label={t('details.landingLabel')}
          hint={t('details.landingHint')}
          checked={draft.landingPage}
          onChange={(landingPage) => update({landingPage})}
        />
        <div className="ccard__divider">
          <SwitchRow
            label={t('details.disclaimerLabel')}
            hint={t('details.disclaimerHint')}
            checked={draft.disclaimerOn}
            onChange={(disclaimerOn) => {
              update({disclaimerOn});
              setPreviewTab(disclaimerOn ? 'disclaimer' : 'cover');
            }}
          />
          {draft.disclaimerOn ? (
            <Field
              label={t('details.disclaimerText')}
              required
              error={disclaimerMissing ? t('details.disclaimerRequired') : null}
            >
              <TextArea
                rows={3}
                value={draft.disclaimer}
                placeholder={t('details.disclaimerPlaceholder')}
                onChange={(e) => update({disclaimer: e.target.value})}
              />
            </Field>
          ) : null}
        </div>
      </section>

      {draft.kind === 'regular' || draft.kind === 'diagnostic' ? (
        <p className="details__note">
          <Icon name="info" size={16} />
          {t('details.movedNote')}
        </p>
      ) : null}
    </div>
  );
}
