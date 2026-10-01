import {CHAT_FILE_TYPES, MAX_CHAT_FILES} from '@console/entities/chat';
import {Icon, IconButton} from '@shared/ui';
import {type ChangeEvent, useRef} from 'react';
import {useTranslation} from 'react-i18next';
import type {ChatAttachments} from '../model/useChatAttachments';

/**
 * The paperclip of a chat composer: opens the file picker (Word, PDF, Markdown, text, CSV; several at once). Past 5
 * documents in the conversation it is disabled and says why.
 */
export function AttachButton({
  attachments,
  disabled = false,
  className,
}: {
  attachments: ChatAttachments;
  disabled?: boolean;
  className?: string;
}) {
  const {t} = useTranslation('features.chat-attachments');
  const picker = useRef<HTMLInputElement>(null);

  const onPick = (event: ChangeEvent<HTMLInputElement>) => {
    attachments.attach(Array.from(event.target.files ?? []));
    event.target.value = '';
  };

  return (
    <>
      <input
        ref={picker}
        type="file"
        className="visually-hidden"
        accept={CHAT_FILE_TYPES}
        multiple
        tabIndex={-1}
        aria-hidden="true"
        onChange={onPick}
      />
      <IconButton
        className={className}
        label={t('attach')}
        icon={<Icon name="paperclip" size={16} />}
        onClick={() => picker.current?.click()}
        disabled={disabled}
        disabledReason={
          !disabled && !attachments.canAttach
            ? t('tooMany', {max: MAX_CHAT_FILES})
            : null
        }
      />
    </>
  );
}
