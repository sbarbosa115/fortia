import {useAccountBrand, usePageViews} from '@respondent/entities/account';
import {
  QuestionnaireRunner,
  RunnerFrame,
  UnavailableScreen,
  GeneratingStage,
} from '@respondent/widgets/questionnaire-runner';

import {useDocumentTitle} from '@shared/lib';
import {LoadingState} from '@shared/ui';
import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {Navigate, useParams} from 'react-router';
import {loginBody, loginFields} from '../model/login';
import {
  completedVariant,
  isCompletedFollowUp,
  loginOutcome,
} from '../model/outcome';
import {useAssignationRun} from '../model/useAssignationRun';
import {CompletedScreen} from './CompletedScreen';
import {LoginSlide} from './LoginSlide';
import './assignation.css';

function Skeleton() {
  return (
    <div className="boot-skeleton">
      <LoadingState />
    </div>
  );
}

/**
 * /a/:id and /a/:id/generating — an assignation (PRD §9.10): the account's brand and language; a completed
 * follow-up's screen by its review status; the automatic resume, or the login slide; then the questionnaire with the
 * respondent token (no resume modal, locked and rejected answers of a retry), and the next stages of its flow.
 */
export function AssignationPage() {
  const {t} = useTranslation('pages.assignation');
  const {id = ''} = useParams();
  const view = useAssignationRun(id);
  const {assignation, started, run, flow} = view;
  const brand = useAccountBrand(assignation?.customer_id ?? null);
  usePageViews(true);
  useDocumentTitle(
    assignation
      ? t('documentTitle', {
          title: started.query.data?.session.title ?? assignation.name,
        })
      : null,
  );

  if (view.assignationQuery.isError) {
    return (
      <div className="respondent-page">
        <UnavailableScreen error={view.assignationQuery.error} />
      </div>
    );
  }
  if (!assignation || !brand.ready) {
    return <Skeleton />;
  }
  const frame = (content: ReactNode) => (
    <RunnerFrame logoUrl={brand.logoUrl} title={assignation.name}>
      {content}
    </RunnerFrame>
  );

  const completed =
    view.completedOnLogin ||
    isCompletedFollowUp(assignation) ||
    (started.query.isError &&
      loginOutcome(started.query.error) === 'completed');
  if (completed) {
    return frame(
      <CompletedScreen variant={completedVariant(assignation.review_status)} />,
    );
  }

  if (!view.entry) {
    // The login is a page of its own, as skyline-ui draws it: no questionnaire frame around it.
    const fields = loginFields(assignation.questions[0]);
    return (
      <LoginSlide
        fields={fields}
        submitting={view.login.isPending}
        outcome={view.loginError}
        onSubmit={(values) => view.login.mutate(loginBody(fields, values))}
      />
    );
  }
  if (!view.flowReady) {
    return <Skeleton />;
  }

  if (view.generating || run?.pendingStateId) {
    if (!run?.pendingStateId || !flow) {
      return <Navigate to={`/a/${id}`} replace />;
    }
    if (!view.generating) {
      return <Navigate to={`/a/${id}/generating`} replace />;
    }
    return frame(
      <GeneratingStage
        flow={flow}
        run={run}
        token={view.entry.token}
        onReady={view.onStageReady}
      />,
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
    return <Skeleton />;
  }
  return (
    <QuestionnaireRunner
      key={`${data.session.session_id}:${view.round}`}
      session={data.session}
      storageKey={started.storageKey}
      initialPosition={data.position}
      savedAt={data.savedAt}
      showResume={false}
      token={view.entry.token}
      stage={view.stage}
      logoUrl={brand.logoUrl}
      maxFiles={brand.maxFiles}
      onStartOver={view.startOver}
      onSubmitted={view.onSubmitted}
    />
  );
}
