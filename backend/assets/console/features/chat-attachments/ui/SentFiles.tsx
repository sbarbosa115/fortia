import type {ChatFile} from '@console/entities/chat';
import {Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import './chat-attachments.css';

/** The documents a sent message attached, under its text. */
export function SentFiles({files}: {files?: ChatFile[]}) {
  const {t} = useTranslation('features.chat-attachments');
  if (!files || files.length === 0) {
    return null;
  }
  return (
    <ul className="chat-attachments__sent" aria-label={t('sent')}>
      {files.map((file, index) => (
        <li key={`${file.filename}-${index}`}>
          <Icon name="file" size={14} />
          {file.filename}
        </li>
      ))}
    </ul>
  );
}
