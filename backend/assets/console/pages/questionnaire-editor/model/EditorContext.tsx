import {createContext, type ReactNode, useContext} from 'react';
import {type Editor, type EditorMode, useEditor} from './useEditor';
import type {Draft} from './types';

const EditorContext = createContext<Editor | null>(null);

/** Shares one editor's state with the header, the steps, the preview and the success screen. */
export function EditorProvider({
  initial,
  mode,
  questionnaireId,
  locked = false,
  children,
}: {
  initial: Draft;
  mode: EditorMode;
  questionnaireId: string | null;
  locked?: boolean;
  children: ReactNode;
}) {
  const editor = useEditor({
    initial,
    mode,
    questionnaireId,
    initialLocked: locked,
  });
  return (
    <EditorContext.Provider value={editor}>{children}</EditorContext.Provider>
  );
}

export function useEditorContext(): Editor {
  const editor = useContext(EditorContext);
  if (!editor) {
    throw new Error('useEditorContext() needs an <EditorProvider> above it.');
  }
  return editor;
}
