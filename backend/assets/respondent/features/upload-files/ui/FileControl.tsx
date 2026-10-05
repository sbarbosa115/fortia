import {Button, Icon, IconButton, ProgressBar} from '@shared/ui';
import {
  type DragEvent,
  useCallback,
  useEffect,
  useId,
  useRef,
  useState,
} from 'react';
import {useTranslation} from 'react-i18next';
import {downloadTemplate, uploadAnswerFile} from '../api/upload';
import {
  acceptFiles,
  keysOf,
  namePasted,
  pastedFiles,
  previewOf,
  type Upload,
  uploadsFromKeys,
} from '../model/files';

type Notice = {key: string; count?: number} | null;

/**
 * A file answer (PRD §9.9): choose several files, drag and drop them, or paste a screenshot anywhere on the page
 * (Ctrl+V, as many times as needed); an image shows its thumbnail.
 * Each file uploads with its progress, can be retried or removed; the saved value is the list of object keys. Next
 * waits until at least one file is uploaded and none is still uploading. A question with a template offers it first:
 * the respondent downloads it, fills it in and uploads it.
 */
export function FileControl({
  label,
  value,
  max,
  disabled,
  target,
  template = null,
  token = null,
  onChange,
  onBusyChange,
}: {
  label: string;
  value: unknown;
  max: number;
  disabled: boolean;
  target: {customerId: string; sessionId: string; questionId: string};
  template?: {key: string; filename: string} | null;
  token?: string | null;
  onChange: (keys: string[]) => void;
  onBusyChange: (busy: boolean) => void;
}) {
  const {t} = useTranslation('features.upload-files');
  const inputId = useId();
  const inputRef = useRef<HTMLInputElement>(null);
  const [uploads, setUploads] = useState<Upload[]>(() =>
    uploadsFromKeys(value),
  );
  const [notice, setNotice] = useState<Notice>(null);
  const [dragging, setDragging] = useState(false);
  const [templateState, setTemplateState] = useState<
    'idle' | 'loading' | 'error'
  >('idle');
  const busy = uploads.some((upload) => upload.status === 'uploading');
  const full = uploads.length >= max;

  useEffect(() => {
    onBusyChange(busy);
  }, [busy, onBusyChange]);

  const saved = JSON.stringify(Array.isArray(value) ? value : []);
  const keys = keysOf(uploads);
  useEffect(() => {
    if (JSON.stringify(keys) !== saved) {
      onChange(keys);
    }
    // keys is derived from uploads: compare by content.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(keys), saved]);

  const patch = (id: string, change: Partial<Upload>) =>
    setUploads((list) =>
      list.map((upload) =>
        upload.id === id ? {...upload, ...change} : upload,
      ),
    );

  const send = useCallback(
    (upload: Upload) => {
      if (!upload.file) {
        return;
      }
      uploadAnswerFile(
        upload.file,
        target,
        (progress) => patch(upload.id, {progress}),
        token,
      )
        .then((key) => patch(upload.id, {status: 'done', key, progress: 100}))
        .catch(() => patch(upload.id, {status: 'error'}));
    },
    [target, token],
  );

  const add = useCallback(
    (files: File[]) => {
      if (disabled || files.length === 0) {
        return;
      }
      const {accepted, tooLarge, discarded} = acceptFiles(
        uploads.length,
        files,
        max,
      );
      setNotice(
        tooLarge > 0
          ? {key: 'tooLarge'}
          : discarded > 0
            ? {key: 'discarded', count: discarded}
            : null,
      );
      const added = accepted.map((file): Upload => ({
        id: `${file.name}-${Math.random().toString(36).slice(2)}`,
        name: file.name,
        status: 'uploading',
        progress: 0,
        key: null,
        file,
        preview: previewOf(file),
      }));
      setUploads((list) => [...list, ...added]);
      added.forEach(send);
    },
    [disabled, max, send, uploads.length],
  );

  useEffect(() => {
    const onPaste = (event: ClipboardEvent) => {
      const files = pastedFiles(event.clipboardData);
      if (files.length > 0) {
        event.preventDefault();
        add(namePasted(files, new Date()));
      }
    };
    document.addEventListener('paste', onPaste);
    return () => document.removeEventListener('paste', onPaste);
  }, [add]);

  const previews = useRef<string[]>([]);
  useEffect(() => {
    previews.current = uploads.flatMap((upload) =>
      upload.preview ? [upload.preview] : [],
    );
  }, [uploads]);
  useEffect(
    () => () => previews.current.forEach((url) => URL.revokeObjectURL(url)),
    [],
  );

  const remove = (upload: Upload) => {
    if (upload.preview) {
      URL.revokeObjectURL(upload.preview);
    }
    setUploads((list) => list.filter((u) => u.id !== upload.id));
  };

  const onDrop = (event: DragEvent) => {
    event.preventDefault();
    setDragging(false);
    add(Array.from(event.dataTransfer.files));
  };

  const getTemplate = () => {
    if (!template) {
      return;
    }
    setTemplateState('loading');
    downloadTemplate(template.key, token)
      .then(() => setTemplateState('idle'))
      .catch(() => setTemplateState('error'));
  };

  return (
    <div className="files" role="group" aria-label={label}>
      {template ? (
        <div className="files__template">
          <Icon name="file" size={20} />
          <div className="files__template-body">
            <span className="files__template-title">{t('template.title')}</span>
            <span className="muted">{t('template.help')}</span>
          </div>
          <Button
            size="sm"
            icon={<Icon name="download" />}
            loading={templateState === 'loading'}
            aria-label={t('template.download', {name: template.filename})}
            onClick={getTemplate}
          >
            {template.filename}
          </Button>
        </div>
      ) : null}
      {templateState === 'error' ? (
        <p className="answer-error" role="alert">
          {t('template.failed')}
        </p>
      ) : null}
      <div
        className="files__drop"
        data-dragging={dragging || undefined}
        onDragOver={(event) => {
          event.preventDefault();
          setDragging(true);
        }}
        onDragLeave={() => setDragging(false)}
        onDrop={onDrop}
      >
        <Icon name="upload" size={28} />
        <input
          ref={inputRef}
          id={inputId}
          type="file"
          multiple
          className="visually-hidden"
          disabled={disabled || full}
          onChange={(event) => {
            add(Array.from(event.target.files ?? []));
            event.target.value = '';
          }}
        />
        <Button
          variant="primary"
          disabled={disabled || full}
          onClick={() => inputRef.current?.click()}
        >
          {t('choose')}
        </Button>
        <span className="muted">{t('paste')}</span>
        <span className="files__help">{t('help')}</span>
      </div>

      {/* How many are attached; the limit only once it is reached (it is a cap, not a target). */}
      {uploads.length > 0 ? (
        <div className="files__meta">
          {full ? (
            <span role="status">{t('limit', {max})}</span>
          ) : (
            <span>{t('counter', {count: uploads.length})}</span>
          )}
        </div>
      ) : null}
      {notice ? (
        <p className="answer-error" role="alert">
          {t(notice.key, {count: notice.count ?? 0, max})}
        </p>
      ) : null}

      {uploads.length > 0 ? (
        <ul className="files__list">
          {uploads.map((upload) => (
            <li key={upload.id} className="files__item">
              {upload.preview ? (
                <img className="files__thumb" src={upload.preview} alt="" />
              ) : (
                <Icon name="file" />
              )}
              <div className="files__item-body">
                <span className="files__name">{upload.name}</span>
                {upload.status === 'uploading' ? (
                  <>
                    <ProgressBar
                      value={upload.progress}
                      label={t('uploading', {progress: upload.progress})}
                    />
                    <span className="muted">
                      {t('uploading', {progress: upload.progress})}
                    </span>
                  </>
                ) : null}
                {upload.status === 'error' ? (
                  <span className="answer-error" role="alert">
                    {t('failed')}
                  </span>
                ) : null}
              </div>
              {upload.status === 'error' ? (
                <Button
                  size="sm"
                  onClick={() => {
                    patch(upload.id, {status: 'uploading', progress: 0});
                    send(upload);
                  }}
                >
                  {t('retry')}
                </Button>
              ) : null}
              <IconButton
                size="sm"
                label={t('remove', {name: upload.name})}
                icon={<Icon name="close" />}
                disabled={disabled || upload.status === 'uploading'}
                onClick={() => remove(upload)}
              />
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
