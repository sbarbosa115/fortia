import {
  trackLead,
  useAccountBrand,
  usePageViews,
} from '@respondent/entities/account';
import {rememberResult, useStartedSession} from '@respondent/entities/session';
import {
  QuestionnaireRunner,
  UnavailableScreen,
} from '@respondent/widgets/questionnaire-runner';
import {useDocumentTitle} from '@shared/lib';
import {LoadingState} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useNavigate, useParams} from 'react-router';

/**
 * /q/:id — a questionnaire by id (PRD §9.3): the session is restored from the browser or started, the account's
 * brand applied, and the respondent answers it; on finishing, the canonical results link opens.
 */
export function QuestionnairePage() {
  const {t} = useTranslation('pages.questionnaire');
  const {id = ''} = useParams();
  const navigate = useNavigate();
  const {query, storageKey, startOver, release} = useStartedSession(id);
  const started = query.data;
  const brand = useAccountBrand(started?.session.customer_id ?? null);
  usePageViews(true);
  useDocumentTitle(
    started ? t('documentTitle', {title: started.session.title}) : null,
  );

  if (query.isError) {
    return (
      <div className="respondent-page">
        <UnavailableScreen error={query.error} />
      </div>
    );
  }
  if (!started || !brand.ready) {
    return (
      <div className="boot-skeleton">
        <LoadingState />
      </div>
    );
  }
  return (
    <QuestionnaireRunner
      key={started.session.session_id}
      session={started.session}
      storageKey={storageKey}
      initialPosition={started.position}
      savedAt={started.savedAt}
      logoUrl={brand.logoUrl}
      maxFiles={brand.maxFiles}
      onStartOver={startOver}
      onSubmitted={({session, result}) => {
        rememberResult({
          sessionId: session.session_id,
          customerId: session.customer_id,
          result,
        });
        trackLead();
        release();
        navigate(`/session/${session.session_id}/results`);
      }}
    />
  );
}
