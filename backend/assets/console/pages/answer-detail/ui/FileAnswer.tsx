import {
  fileKind,
  fileName,
  requestDownloadUrl,
  type FileKind,
} from '@console/entities/answer';
import {Button, Icon, Modal, Spinner, useToast} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';

/**
 * One cell per stored file (PRD §10.8): "View" previews images, PDFs, audio and video; "Download" downloads. Both ask
 * for a signed URL on click (15 minutes).
 */
export function FileAnswer({keys}: {keys: string[]}) {
  return (
    <ul className="detail-files">
      {keys.map((key) => (
        <FileItem key={key} fileKey={key} />
      ))}
    </ul>
  );
}

function FileItem({fileKey}: {fileKey: string}) {
  const {t} = useTranslation('pages.answer-detail');
  const toast = useToast();
  const kind = fileKind(fileKey);
  const [preview, setPreview] = useState<string | null>(null);
  const [opening, setOpening] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const name = fileName(fileKey);

  const view = async () => {
    setOpening(true);
    try {
      setPreview((await requestDownloadUrl(fileKey, 'inline')).url);
    } catch (error) {
      toast.apiError(error);
    } finally {
      setOpening(false);
    }
  };
  const download = async () => {
    setDownloading(true);
    try {
      const {url} = await requestDownloadUrl(fileKey, 'attachment');
      const link = document.createElement('a');
      link.href = url;
      link.rel = 'noopener';
      link.download = name;
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch (error) {
      toast.apiError(error);
    } finally {
      setDownloading(false);
    }
  };

  return (
    <li className="detail-file">
      <Icon name="file" size={16} />
      <span className="detail-file__name">{name}</span>
      {kind === 'other' ? (
        <span className="muted">{t('files.noPreview')}</span>
      ) : (
        <Button
          size="sm"
          variant="ghost"
          loading={opening}
          onClick={() => void view()}
        >
          {t('files.view')}
        </Button>
      )}
      <Button
        size="sm"
        variant="ghost"
        icon={<Icon name="download" size={16} />}
        loading={downloading}
        onClick={() => void download()}
      >
        {t('files.download')}
      </Button>
      <Modal
        open={preview !== null}
        title={name}
        onClose={() => setPreview(null)}
        wide
      >
        {preview ? (
          <Preview kind={kind} url={preview} name={name} />
        ) : (
          <Spinner />
        )}
      </Modal>
    </li>
  );
}

function Preview({
  kind,
  url,
  name,
}: {
  kind: FileKind;
  url: string;
  name: string;
}) {
  const {t} = useTranslation('pages.answer-detail');
  switch (kind) {
    case 'image':
      return <img className="detail-preview" src={url} alt={name} />;
    case 'pdf':
      return (
        <iframe
          className="detail-preview detail-preview--pdf"
          src={url}
          title={name}
        />
      );
    case 'audio':
      return (
        <audio
          className="detail-preview"
          src={url}
          controls
          aria-label={name}
        />
      );
    case 'video':
      return (
        <video className="detail-preview" src={url} controls aria-label={name}>
          <track kind="captions" />
        </video>
      );
    default:
      return <p>{t('files.noPreview')}</p>;
  }
}
