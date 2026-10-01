import {isApiError} from '@shared/api';
import {appConfig} from '@shared/config';
import {useTranslation} from 'react-i18next';
import {StatusScreen} from './RunnerFrame';

/**
 * Why a questionnaire cannot be answered (PRD §9.3 rendering priority 1–2, §9.10, §9.11): the response limit (429)
 * or "This questionnaire does not exist" with a way to create one in the console.
 */
export function UnavailableScreen({
  error,
  notFound = 'questionnaire',
}: {
  error: unknown;
  notFound?: 'questionnaire' | 'flow';
}) {
  const {t} = useTranslation('widgets.questionnaire-runner');
  if (isApiError(error) && error.status === 429) {
    return <StatusScreen title={t('unavailable.limit')} />;
  }
  if (notFound === 'flow') {
    return <StatusScreen title={t('unavailable.flow')} />;
  }
  return (
    <StatusScreen
      title={t('unavailable.notFound')}
      action={
        <a className="btn btn--secondary" href={appConfig().consoleUrl}>
          {t('unavailable.create')}
        </a>
      }
    />
  );
}
