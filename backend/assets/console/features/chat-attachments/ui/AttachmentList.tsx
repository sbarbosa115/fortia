import {MAX_CHAT_FILES} from '@console/entities/chat';
import {joinClasses} from '@shared/lib';
import {Icon, IconButton, Spinner} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {ChatAttachments} from '../model/useChatAttachments';
import './chat-attachments.css';

/**
 * The documents waiting for the next message, above the composer: each one reading, ready, or refused with its reason,
 * and a button to remove it; then the notice when a pick had more files than room.
 */
export function AttachmentList({attachments}: {attachments: ChatAttachments}) {
  const {t} = useTranslation('features.chat-attachments');

  return (
    <>
      {attachments.attachments.length > 0 ? (
        <ul className="chat-attachments" aria-label={t('list')}>
          {attachments.attachments.map((attachment) => (
            <li
              key={attachment.id}
              className={joinClasses(
                'chat-attachments__item',
                attachment.status === 'failed' &&
                  'chat-attachments__item--failed',
              )}
            >
              {attachment.status === 'uploading' ? (
                <Spinner size={14} />
              ) : (
                <Icon
                  name={
                    attachment.status === 'failed' ? 'alert-circle' : 'file'
                  }
                  size={14}
                />
              )}
              <span className="chat-attachments__name">{attachment.name}</span>
              <span className="chat-attachments__status">
                {attachment.status === 'uploading'
                  ? t('reading')
                  : attachment.status === 'failed'
                    ? t(attachment.errorKey ?? 'errors.HTTP_ERROR', {
                        ns: 'shared',
                      })
                    : t('ready')}
              </span>
              <IconButton
                size="sm"
                label={t('remove', {name: attachment.name})}
                icon={<Icon name="close" size={14} />}
                onClick={() => attachments.remove(attachment.id)}
              />
            </li>
          ))}
        </ul>
      ) : null}
      {attachments.tooMany ? (
        <p className="chat-attachments__notice" role="alert">
          {t('tooMany', {max: MAX_CHAT_FILES})}
        </p>
      ) : null}
    </>
  );
}
