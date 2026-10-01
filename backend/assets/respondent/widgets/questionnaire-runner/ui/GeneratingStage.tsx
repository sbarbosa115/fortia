import {
  type Flow,
  type FlowRun,
  readFlowRun,
  requestStage,
  writeFlowRun,
} from '@respondent/entities/flow';
import {pollJob} from '@shared/api';
import {RESPONDENT_POLL} from '../model/useRunner';
import {StatusAction, StatusScreen} from './RunnerFrame';
import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';

const MESSAGES = 5;
export const ROTATE_MS = 3500;

/**
 * "Preparing your next questions" (PRD §9.11 step 5): POST /questionnaire/prompt with the answers, then the job is
 * polled until it has the generated questionnaire_id. The job id is saved in the flow run, so a reload resumes the
 * polling; a failure offers "Try again".
 */
export function GeneratingStage({
  flow,
  run,
  token = null,
  onReady,
}: {
  flow: Flow;
  run: FlowRun;
  token?: string | null;
  onReady: (next: FlowRun) => void;
}) {
  const {t} = useTranslation('widgets.questionnaire-runner');
  const [message, setMessage] = useState(0);
  const [failed, setFailed] = useState(false);
  const [attempt, setAttempt] = useState(0);
  // The latest props, read by the generation without restarting it on every render.
  const props = useRef({flow, run, token, onReady});
  useEffect(() => {
    props.current = {flow, run, token, onReady};
  });

  useEffect(() => {
    const timer = window.setInterval(
      () => setMessage((current) => (current + 1) % MESSAGES),
      ROTATE_MS,
    );
    return () => window.clearInterval(timer);
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    const {flow, run, token, onReady} = props.current;
    const generate = async () => {
      // The saved run is the truth: it holds the job id (resumed after a reload) or none after a failure.
      let current = readFlowRun(flow, run.identifier) ?? run;
      if (!current.pendingJobId) {
        const {job} = await requestStage(
          flow.questionnaire_id,
          current.pendingAnswers ?? [],
          current.pendingSessionId,
          token,
        );
        current = {...current, pendingJobId: job.job_id};
        writeFlowRun(current);
      }
      const result = await pollJob(current.pendingJobId as string, {
        ...RESPONDENT_POLL,
        token,
        signal: controller.signal,
      });
      const generated = result['questionnaire_id'];
      if (typeof generated !== 'string' || generated === '') {
        throw new Error('The stage was not generated.');
      }
      const next: FlowRun = {
        ...current,
        activeStateId: current.pendingStateId ?? current.activeStateId,
        questionnaireId: generated,
        stage: current.stage + 1,
        pendingJobId: null,
        pendingStateId: null,
        pendingSessionId: null,
        pendingAnswers: null,
      };
      writeFlowRun(next);
      onReady(next);
    };
    generate().catch((error: unknown) => {
      if ((error as {name?: string})?.name === 'AbortError') {
        return;
      }
      // A failed job is not polled again: "Try again" asks for a new one.
      writeFlowRun({...run, pendingJobId: null});
      setFailed(true);
    });
    return () => controller.abort();
  }, [attempt]);

  if (failed) {
    return (
      <StatusScreen
        title={t('generating.failed')}
        action={
          <StatusAction
            onClick={() => {
              setFailed(false);
              setAttempt((current) => current + 1);
            }}
          >
            {t('generating.retry')}
          </StatusAction>
        }
      />
    );
  }
  return (
    <StatusScreen
      busy
      eyebrow={t('generating.eyebrow')}
      title={t('generating.title')}
      subtitle={t(`generating.messages.${message}`)}
    />
  );
}
