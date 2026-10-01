import {
  type Assignation,
  canResume,
  clearAssignationProgress,
  clearRespondentToken,
  fetchAssignation,
  type LoginBody,
  readAssignationProgress,
  readRespondentToken,
  type RespondentLogin,
  saveRespondentToken,
  signIn,
  tokenClaims,
  writeAssignationProgress,
} from '@respondent/entities/assignation';
import {trackLead} from '@respondent/entities/account';
import {
  clearFlowRun,
  entryState,
  fetchFlow,
  type Flow,
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
  readSnapshot,
  rememberResult,
  saveSession,
  type Session,
  snapshotKey,
  startedSessionKey,
  useStartedSession,
  writeSnapshot,
} from '@respondent/entities/session';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {useCallback, useMemo, useState} from 'react';
import {useLocation, useNavigate} from 'react-router';
import {type LoginOutcome, loginOutcome} from './outcome';
import {clearAnswers, landingPosition, mergeLocalAnswers} from './start';

/** Who is answering: the respondent token and the questionnaire (root or a stage of its flow) they are on. */
type Entry = {token: string; questionnaireId: string; flowId: string | null};

export function assignationQueryKey(assignationId: string) {
  return ['respondent', 'assignation', assignationId] as const;
}

const flowQueryKey = (flowId: string | null) =>
  ['respondent', 'flow', flowId] as const;

/** A flow run is this assignation's when it was saved under its identifier (`a:{id}`). */
const runIdentifier = (assignationId: string) => `a:${assignationId}`;

function savedRunOf(assignationId: string): FlowRun | null {
  const run = readFlowRun(null, runIdentifier(assignationId));
  return run?.identifier === runIdentifier(assignationId) ? run : null;
}

/** §9.10 step 3: the saved token and progress, when they let the respondent go on without logging in. */
function resumedEntry(assignation: Assignation): Entry | null {
  const id = assignation.assignations_id;
  const token = readRespondentToken();
  const progress = readAssignationProgress(id);
  if (!token || !progress) {
    return null;
  }
  const local =
    readSnapshot(snapshotKey(progress.questionnaireId, id)) !== null ||
    Boolean(savedRunOf(id)?.pendingStateId);
  return canResume(tokenClaims(token), assignation, local)
    ? {
        token,
        questionnaireId: progress.questionnaireId,
        flowId: progress.flowId ?? null,
      }
    : null;
}

/**
 * The respondent's /a/:id and /a/:id/generating (PRD §9.10, §9.11 behind /a/): the assignation and its brand, the
 * completed screens, the automatic resume, the login and its outcomes, then the questionnaire — and, when its flow
 * goes on, the next stages with the same token. On finishing, the token is deleted and the results open.
 */
export function useAssignationRun(assignationId: string) {
  const {pathname} = useLocation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const generating = pathname.endsWith('/generating');

  const assignationQuery = useQuery({
    queryKey: assignationQueryKey(assignationId),
    queryFn: () => fetchAssignation(assignationId),
    retry: false,
  });
  const assignation = assignationQuery.data ?? null;

  const [signedIn, setSignedIn] = useState<Entry | null | undefined>(undefined);
  const resumed = useMemo(
    () => (assignation ? resumedEntry(assignation) : null),
    [assignation],
  );
  const entry = signedIn === undefined ? resumed : signedIn;

  const [loginError, setLoginError] = useState<LoginOutcome | null>(null);
  const [completedOnLogin, setCompletedOnLogin] = useState(false);
  const [round, setRound] = useState(0);

  const flowId = entry?.flowId ?? null;
  const flowQuery = useQuery({
    queryKey: flowQueryKey(flowId),
    queryFn: () => fetchFlow(flowId ?? ''),
    enabled: flowId !== null,
    staleTime: Infinity,
    retry: false,
  });
  const flow: Flow | null = flowQuery.data ?? null;
  const flowReady = flowId === null || flowQuery.isFetched;

  const [savedRun, setRun] = useState<FlowRun | null>(() =>
    savedRunOf(assignationId),
  );
  const run = flow && savedRun?.flowId === flow.id ? savedRun : null;
  const entryStateOf = flow ? entryState(flow) : null;
  const activeStateId = run?.activeStateId ?? entryStateOf?.state_id ?? null;
  const questionnaireId =
    run?.questionnaireId ?? entry?.questionnaireId ?? null;
  const token = entry?.token ?? null;

  const started = useStartedSession(
    entry && flowReady && !generating && !run?.pendingStateId
      ? questionnaireId
      : null,
    {token, assignationId},
  );

  const login = useMutation({
    mutationFn: (body: LoginBody) => signIn(assignationId, body),
    onMutate: () => setLoginError(null),
    onSuccess: (response: RespondentLogin) => {
      const session = response.questionnaire;
      const key = snapshotKey(session.questionnaire_id, assignationId);
      const local = readSnapshot(key);
      const sameAttempt =
        local?.questionnaire.session_id === session.session_id;
      const merged = mergeLocalAnswers(session, local?.questionnaire ?? null);
      writeSnapshot(
        key,
        merged,
        sameAttempt && local ? local.currentPosition : landingPosition(merged),
      );
      saveRespondentToken(response.token);
      const flowOfLogin = response.flow ?? null;
      if (flowOfLogin) {
        queryClient.setQueryData(flowQueryKey(flowOfLogin.id), flowOfLogin);
      }
      writeAssignationProgress({
        assignationId,
        questionnaireId: session.questionnaire_id,
        flowId: flowOfLogin?.id ?? null,
      });
      // A login starts again from the attempt's session: a stage reached before is not resumed.
      if (savedRunOf(assignationId)) {
        clearFlowRun();
      }
      setRun(null);
      queryClient.removeQueries({
        queryKey: startedSessionKey(session.questionnaire_id, assignationId),
      });
      setSignedIn({
        token: response.token,
        questionnaireId: session.questionnaire_id,
        flowId: flowOfLogin?.id ?? null,
      });
    },
    onError: (error: unknown) => {
      const outcome = loginOutcome(error);
      if (outcome === 'completed') {
        setCompletedOnLogin(true);
        void queryClient.invalidateQueries({
          queryKey: assignationQueryKey(assignationId),
        });
      } else {
        setLoginError(outcome);
      }
    },
  });

  const remember = useCallback(
    (next: FlowRun) => {
      setRun(next);
      writeAssignationProgress({
        assignationId,
        questionnaireId: next.questionnaireId,
        flowId: next.flowId,
      });
    },
    [assignationId],
  );

  const onStageReady = useCallback(
    (next: FlowRun) => {
      remember(next);
      navigate(`/a/${assignationId}`, {replace: true});
    },
    [assignationId, navigate, remember],
  );

  const onSubmitted = ({
    session,
    result,
  }: {
    session: Session;
    result: Record<string, unknown>;
  }) => {
    const state = flow ? stateById(flow, activeStateId) : null;
    const step =
      flow && state ? stepAfter(flow, state) : ({kind: 'end'} as const);
    started.release();
    if (step.kind === 'end' || !flow || !activeStateId) {
      clearRespondentToken();
      clearAssignationProgress(assignationId);
      if (savedRunOf(assignationId)) {
        clearFlowRun();
      }
      void queryClient.invalidateQueries({
        queryKey: assignationQueryKey(assignationId),
      });
      rememberResult({
        sessionId: session.session_id,
        customerId: session.customer_id,
        result,
      });
      trackLead();
      navigate(`/session/${session.session_id}/results`);
      return;
    }
    const base: Omit<FlowRun, 'updatedAt'> = {
      flowId: flow.id,
      identifier: runIdentifier(assignationId),
      activeStateId,
      questionnaireId: session.questionnaire_id,
      stage: run?.stage ?? 1,
      pendingJobId: null,
      pendingStateId: null,
      pendingSessionId: null,
      pendingAnswers: null,
    };
    if (step.kind === 'questionnaire') {
      const next = {
        ...base,
        activeStateId: step.state.state_id,
        questionnaireId: questionnaireIdOf(flow, step.state),
      };
      writeFlowRun(next);
      remember({...next, updatedAt: Date.now()});
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
    navigate(`/a/${assignationId}/generating`);
  };

  /** "Start over" keeps the session and the locked answers and clears the rest in place (§9.10 step 5). */
  const startOver = () => {
    const key = started.storageKey;
    const current =
      readSnapshot(key)?.questionnaire ?? started.query.data?.session;
    if (!current) {
      return;
    }
    const cleared = clearAnswers(current);
    writeSnapshot(key, cleared, 0);
    void saveSession(cleared, token).catch(() => undefined);
    void queryClient.resetQueries({
      queryKey: startedSessionKey(current.questionnaire_id, assignationId),
    });
    setRound((value) => value + 1);
  };

  return {
    assignationQuery,
    assignation,
    entry,
    generating,
    flow,
    flowReady,
    run,
    started,
    stage: flow ? {current: run?.stage ?? 1, total: stageCount(flow)} : null,
    round,
    login,
    loginError,
    completedOnLogin,
    onSubmitted,
    onStageReady,
    startOver,
  };
}
