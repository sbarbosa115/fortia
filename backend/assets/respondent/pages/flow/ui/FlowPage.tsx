import {
  trackLead,
  useAccountBrand,
  usePageViews,
} from '@respondent/entities/account';
import {
  clearFlowRun,
  entryState,
  fetchFlow,
  type FlowRun,
  questionnaireIdOf,
  readFlowRun,
  stageCount,
  stateById,
  stepAfter,
  writeFlowRun,
} from '@respondent/entities/flow';
import {
  flattenAnswers,
  rememberResult,
  type Session,
  useStartedSession,
} from '@respondent/entities/session';
import {
  QuestionnaireRunner,
  RunnerFrame,
  UnavailableScreen,
} from '@respondent/widgets/questionnaire-runner';
import {useDocumentTitle} from '@shared/lib';
import {LoadingState} from '@shared/ui';
import {useQuery} from '@tanstack/react-query';
import {useCallback, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Navigate, useLocation, useNavigate, useParams} from 'react-router';
import {GeneratingStage} from './GeneratingStage';

/**
 * /f/:id and /f/:id/generating — a multi-stage flow by flow id, slug or questionnaire id (PRD §9.11). Each
 * questionnaire loads in place (the URL does not change); a `prompt` state generates the next stage at
 * /f/:id/generating; the end of the flow opens the results. The run is saved so a reload continues it.
 */
export function FlowPage() {
  const {t} = useTranslation('pages.flow');
  const {id = ''} = useParams();
  const {pathname} = useLocation();
  const navigate = useNavigate();
  const generating = pathname.endsWith('/generating');
  const flowQuery = useQuery({
    queryKey: ['respondent', 'flow', id],
    queryFn: () => fetchFlow(id),
    staleTime: Infinity,
    retry: false,
  });
  const flow = flowQuery.data ?? null;
  const [savedRun, setRun] = useState<FlowRun | null>(() =>
    readFlowRun(null, id),
  );
  const run = flow && savedRun && savedRun.flowId === flow.id ? savedRun : null;
  const entry = flow ? entryState(flow) : null;
  const activeStateId = run?.activeStateId ?? entry?.state_id ?? null;
  const questionnaireId =
    run?.questionnaireId ??
    (flow && entry ? questionnaireIdOf(flow, entry) : null);
  const started = useStartedSession(generating ? null : questionnaireId);
  const brand = useAccountBrand(flow?.customer_id ?? null);
  usePageViews(true);
  useDocumentTitle(
    started.query.data
      ? t('documentTitle', {title: started.query.data.session.title})
      : null,
  );

  const onReady = useCallback(
    (next: FlowRun) => {
      setRun(next);
      navigate(`/f/${id}`, {replace: true});
    },
    [id, navigate],
  );

  const onSubmitted = ({
    session,
    result,
  }: {
    session: Session;
    result: Record<string, unknown>;
  }) => {
    if (!flow || !activeStateId) {
      return;
    }
    const state = stateById(flow, activeStateId);
    const step = state ? stepAfter(flow, state) : ({kind: 'end'} as const);
    const base: Omit<FlowRun, 'updatedAt'> = {
      flowId: flow.id,
      identifier: id,
      activeStateId,
      questionnaireId: session.questionnaire_id,
      stage: run?.stage ?? 1,
      pendingJobId: null,
      pendingStateId: null,
      pendingSessionId: null,
      pendingAnswers: null,
    };
    started.release();
    if (step.kind === 'end') {
      clearFlowRun();
      rememberResult({
        sessionId: session.session_id,
        customerId: session.customer_id,
        result,
      });
      trackLead();
      navigate(`/session/${session.session_id}/results`);
      return;
    }
    if (step.kind === 'questionnaire') {
      const next = {
        ...base,
        activeStateId: step.state.state_id,
        questionnaireId: questionnaireIdOf(flow, step.state),
      };
      writeFlowRun(next);
      setRun({...next, updatedAt: Date.now()});
      return;
    }
    const pending = {
      ...base,
      pendingStateId: step.state.state_id,
      pendingSessionId: session.session_id,
      pendingAnswers: flattenAnswers(session),
    };
    writeFlowRun(pending);
    setRun({...pending, updatedAt: Date.now()});
    navigate(`/f/${id}/generating`);
  };

  if (flowQuery.isError || (flow && !entry)) {
    return (
      <div className="respondent-page">
        <UnavailableScreen error={flowQuery.error} notFound="flow" />
      </div>
    );
  }
  if (!flow || !brand.ready) {
    return (
      <div className="boot-skeleton">
        <LoadingState />
      </div>
    );
  }

  if (generating) {
    if (!run?.pendingStateId) {
      return <Navigate to={`/f/${id}`} replace />;
    }
    return (
      <RunnerFrame logoUrl={brand.logoUrl} title={flow.slug}>
        <GeneratingStage flow={flow} run={run} onReady={onReady} />
      </RunnerFrame>
    );
  }

  if (started.query.isError) {
    return (
      <div className="respondent-page">
        <UnavailableScreen error={started.query.error} />
      </div>
    );
  }
  const data = started.query.data;
  if (!data) {
    return (
      <div className="boot-skeleton">
        <LoadingState />
      </div>
    );
  }
  return (
    <QuestionnaireRunner
      key={data.session.session_id}
      session={data.session}
      storageKey={started.storageKey}
      initialPosition={data.position}
      savedAt={data.savedAt}
      stage={{current: run?.stage ?? 1, total: stageCount(flow)}}
      logoUrl={brand.logoUrl}
      maxFiles={brand.maxFiles}
      onStartOver={started.startOver}
      onSubmitted={onSubmitted}
    />
  );
}
