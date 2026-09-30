import {usePlanUsage} from '@console/entities/plan-usage';
import {useViewer} from '@console/entities/viewer';
import {ErrorState, LoadingState} from '@shared/ui';
import {useState} from 'react';
import {Navigate, useParams} from 'react-router';
import {editRouteKind} from '../model/decode';
import {emptyDraft} from '../model/draft';
import {EditorProvider} from '../model/EditorContext';
import type {EditorKind} from '../model/types';
import {useEditorData} from '../model/useEditorData';
import {CreationEditor} from './CreationEditor';
import {GenericEditor} from './GenericEditor';
import {LockedView} from './LockedView';
import './editor.css';

const CREATE_KINDS: Record<string, {kind: EditorKind; feature: string}> = {
  regular: {kind: 'regular', feature: 'regular'},
  diagnostic: {kind: 'diagnostic', feature: 'diagnostic'},
  chaining: {kind: 'chaining', feature: 'chain'},
};

/**
 * /questionnaires/create/:kind (regular | diagnostic | chaining), /questionnaires/:id/edit (loads, then opens the
 * editor of its type or the generic one) and /questionnaires/:id/edit/:kind (PRD §10.5, §10.7).
 */
export function QuestionnaireEditorPage() {
  const {id, kind} = useParams();
  if (id) {
    return <EditExisting key={`${id}-${kind ?? ''}`} id={id} routeKind={kind} />;
  }
  return <CreateNew key={kind} routeKind={kind ?? ''} />;
}

function CreateNew({routeKind}: {routeKind: string}) {
  const viewer = useViewer();
  const usage = usePlanUsage(!viewer.isAdmin);
  const target = CREATE_KINDS[routeKind];
  const [initial] = useState(() =>
    target ? emptyDraft(target.kind) : null,
  );
  if (!target || !initial) {
    return <Navigate to="/questionnaires/new" replace />;
  }
  if (!viewer.isAdmin && usage.isPending) {
    return <LoadingState />;
  }
  const verdict = usage.data?.features[target.feature];
  if (!viewer.isAdmin && usage.data && !verdict?.allowed) {
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
  if (isPending) {
    return <LoadingState />;
  }
  if (isError) {
    return <ErrorState error={error} onRetry={() => void refetch()} />;
  }
  if (data.locked) {
    return <LockedView questionnaire={data.questionnaire} />;
  }
  const wanted = editRouteKind(data.kind);
  const asked = routeKind === 'chaining' ? 'prompt' : routeKind;
  if (wanted === null) {
    if (routeKind) {
      return <Navigate to={`/questionnaires/${id}/edit`} replace />;
    }
    return (
      <EditorProvider initial={data.draft} mode="edit" questionnaireId={id}>
        <GenericEditor />
      </EditorProvider>
    );
  }
  if (asked !== wanted) {
    return <Navigate to={`/questionnaires/${id}/edit/${wanted}`} replace />;
  }
  return (
    <EditorProvider initial={data.draft} mode="edit" questionnaireId={id}>
      <CreationEditor />
    </EditorProvider>
  );
}
