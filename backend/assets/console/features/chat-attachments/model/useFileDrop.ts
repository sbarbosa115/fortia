import {type DragEvent, useState} from 'react';
import type {ChatAttachments} from './useChatAttachments';

/** Documents dropped on a chat attach like picked ones; `dragging` lets the chat show where they land. */
export function useFileDrop(attachments: ChatAttachments, enabled: boolean) {
  const [dragging, setDragging] = useState(false);
  const canDrop = enabled && attachments.canAttach;

  return {
    dragging,
    dropHandlers: {
      onDragOver: (event: DragEvent) => {
        if (canDrop && event.dataTransfer.types.includes('Files')) {
          event.preventDefault();
          setDragging(true);
        }
      },
      onDragLeave: () => setDragging(false),
      onDrop: (event: DragEvent) => {
        setDragging(false);
        if (canDrop && event.dataTransfer.files.length > 0) {
          event.preventDefault();
          attachments.attach(Array.from(event.dataTransfer.files));
        }
      },
    },
  };
}
