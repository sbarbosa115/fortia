import {
  templateDownloadUrl,
  uploadTemplate,
} from '@console/entities/questionnaire';
import {useViewer} from '@console/entities/viewer';
import {Button, Icon, IconButton, Spinner, useToast} from '@shared/ui';
import {useId, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';
import type {DraftQuestion} from '../model/types';

/** A template is at most 20 MB (the server's limit). */
const MAX_TEMPLATE_BYTES = 20 * 1024 * 1024;

/**
 * A file question's template (optional): a file the respondent downloads, fills in and uploads back. Chosen or dropped
 * on the drop area, it is uploaded at once and shown as a file card (download, replace, remove); the question keeps
 * its key.
 */
export function TemplateField({question}: {question: DraftQuestion}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const viewer = useViewer();
  const toast = useToast();
  const inputId = useId();
  const inputRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);
  const template = question.template;

  const upload = (file: File) => {
    if (file.size > MAX_TEMPLATE_BYTES) {
      toast.error(t('questions.templateTooLarge'));
      return;
    }
    setUploading(true);
    uploadTemplate(viewer.customerId, file)
      .then((uploaded) =>
        editor.updateQuestion(question.key, {template: uploaded}),
      )
      .catch((error: unknown) => toast.apiError(error))
      .finally(() => setUploading(false));
  };
  const open = () => {
    if (template) {
      templateDownloadUrl(template.key)
        .then((url) => window.open(url, '_blank', 'noopener'))
        .catch((error: unknown) => toast.apiError(error));
    }
  };

  const pick = () => inputRef.current?.click();
  const [dragging, setDragging] = useState(false);
  const extension = template?.filename.split('.').pop()?.toUpperCase() ?? '';

  return (
    <fieldset className="options template-field">
      <legend className="field__label">{t('questions.template')}</legend>
      <span className="field__hint">{t('questions.templateHint')}</span>
      <input
        ref={inputRef}
        id={inputId}
        type="file"
        className="visually-hidden"
        aria-label={t('questions.templateUpload')}
        onChange={(event) => {
          const file = event.target.files?.[0];
          event.target.value = '';
          if (file) {
            upload(file);
          }
        }}
      />
      {template ? (
        <div className="template-card" aria-busy={uploading || undefined}>
          <span className="template-card__icon" aria-hidden>
            {uploading ? <Spinner size={18} /> : <Icon name="file" size={18} />}
          </span>
          <span className="template-card__text">
            <span className="template-card__name" title={template.filename}>
              {template.filename}
            </span>
            <span className="template-card__meta">
              {uploading
                ? t('questions.templateUploading')
                : t('questions.templateKind', {ext: extension})}
            </span>
          </span>
          <span className="template-card__actions">
            <IconButton
              size="sm"
              label={t('questions.templateDownload')}
              icon={<Icon name="download" size={16} />}
              onClick={open}
            />
            <Button
              size="sm"
              icon={<Icon name="refresh" size={14} />}
              disabled={uploading}
              onClick={pick}
            >
              {t('questions.templateReplace')}
            </Button>
            <IconButton
              size="sm"
              className="template-card__remove"
              label={t('questions.templateRemove', {name: template.filename})}
              icon={<Icon name="trash" size={16} />}
              disabled={uploading}
              onClick={() =>
                editor.updateQuestion(question.key, {template: null})
              }
            />
          </span>
        </div>
      ) : (
        <button
          type="button"
          className="template-drop"
          data-dragging={dragging || undefined}
          disabled={uploading}
          onClick={pick}
          onDragOver={(event) => {
            event.preventDefault();
            setDragging(true);
          }}
          onDragLeave={() => setDragging(false)}
          onDrop={(event) => {
            event.preventDefault();
            setDragging(false);
            const file = event.dataTransfer.files[0];
            if (file) {
              upload(file);
            }
          }}
        >
          <span className="template-drop__icon" aria-hidden>
            {uploading ? (
              <Spinner size={18} />
            ) : (
              <Icon name="upload" size={18} />
            )}
          </span>
          <span className="template-drop__text">
            <strong>
              {uploading
                ? t('questions.templateUploading')
                : t('questions.templateUpload')}
            </strong>
            <span>{t('questions.templateDrop')}</span>
          </span>
        </button>
      )}
    </fieldset>
  );
}
