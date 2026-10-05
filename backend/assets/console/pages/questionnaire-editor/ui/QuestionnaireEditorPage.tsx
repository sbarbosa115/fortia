import {ErrorState, LoadingState} from '@shared/ui';
import {useState} from 'react';
import {Navigate, useParams} from 'react-router';
import {editRouteKind} from '../model/decode';
import {emptyDraft} from '../model/draft';
import {EditorProvider} from '../model/EditorContext';
import type {EditorKind} from '../model/types';
import {returnToSearch, useReturnTo} from '../model/returnTo';
import {useEditorData} from '../model/useEditorData';
import {CreationEditor} from './CreationEditor';
import {GenericEditor} from './GenericEditor';
import './editor.css';
import './shell.css';

const CREATE_KINDS: Record<string, EditorKind> = {
  regular: 'regular',
  diagnostic: 'diagnostic',
  chaining: 'chaining',
};

/**
 * /questionnaires/create/:kind (regular | diagnostic | chaining), /questionnaires/:id/edit (loads, then opens the
 * editor of its type or the generic one) and /questionnaires/:id/edit/:kind (PRD §10.5, §10.7).
 */
export function QuestionnaireEditorPage() {
  const {id, kind} = useParams();
  if (id) {
    return (
      <EditExisting key={`${id}-${kind ?? ''}`} id={id} routeKind={kind} />
    );
  }
  return <CreateNew key={kind} routeKind={kind ?? ''} />;
}

function CreateNew({routeKind}: {routeKind: string}) {
  const target = CREATE_KINDS[routeKind];
  const [initial] = useState(() => (target ? emptyDraft(target) : null));
  if (!target || !initial) {
    return <Navigate to="/questionnaires/new" replace />;
  }
  return (
    <EditorProvider initial={initial} mode="create" questionnaireId={null}>
      <CreationEditor />
    </EditorProvider>
  );
}

function EditExisting({id, routeKind}: {id: string; routeKind?: string}) {
  const {data, isPending, isError, error, refetch} = useEditorData(id);
  const search = returnToSearch(useReturnTo());
  if (isPending) {
    return <LoadingState />;
  }
  if (isError) {
    return <ErrorState error={error} onRetry={() => void refetch()} />;
  }
  const wanted = editRouteKind(data.kind);
  const asked = routeKind === 'chaining' ? 'prompt' : routeKind;
  if (wanted === null) {
    if (routeKind) {
      return <Navigate to={`/questionnaires/${id}/edit${search}`} replace />;
    }
    return (
      <EditorProvider
        initial={data.draft}
        mode="edit"
        questionnaireId={id}
        locked={data.locked}
      >
        <GenericEditor />
      </EditorProvider>
    );
  }
  if (asked !== wanted) {
    return (
      <Navigate to={`/questionnaires/${id}/edit/${wanted}${search}`} replace />
    );
  }
  return (
    <EditorProvider
      initial={data.draft}
      mode="edit"
      questionnaireId={id}
      locked={data.locked}
    >
      <CreationEditor />
    </EditorProvider>
  );
}
