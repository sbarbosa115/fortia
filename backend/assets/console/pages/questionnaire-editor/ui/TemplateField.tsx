import {
  templateDownloadUrl,
  uploadTemplate,
} from '@console/entities/questionnaire';
import {useViewer} from '@console/entities/viewer';
import {Button, Icon, IconButton, useToast} from '@shared/ui';
import {useId, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';
import type {DraftQuestion} from '../model/types';

/** A template is at most 20 MB (the server's limit). */
const MAX_TEMPLATE_BYTES = 20 * 1024 * 1024;

/**
 * A file question's template (optional): a file the respondent downloads, fills in and uploads back. It is uploaded
 * at once, and the question keeps its key.
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

  return (
    <fieldset className="options">
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
        <div className="options__row template-field">
          <Icon name="file" size={16} />
          <button type="button" className="template-field__name" onClick={open}>
            {template.filename}
          </button>
          <Button
            size="sm"
            variant="ghost"
            loading={uploading}
            onClick={() => inputRef.current?.click()}
          >
            {t('questions.templateReplace')}
          </Button>
          <IconButton
            size="sm"
            label={t('questions.templateRemove', {name: template.filename})}
            icon={<Icon name="close" />}
            onClick={() =>
              editor.updateQuestion(question.key, {template: null})
            }
          />
        </div>
      ) : (
        <div>
          <Button
            size="sm"
            icon={<Icon name="upload" />}
            loading={uploading}
            onClick={() => inputRef.current?.click()}
          >
            {t('questions.templateUpload')}
          </Button>
        </div>
      )}
    </fieldset>
  );
}
