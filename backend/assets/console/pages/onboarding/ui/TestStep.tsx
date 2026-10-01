import {Button, Icon, Spinner} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useAnswerWatch} from '../model/useAnswerWatch';
import {NAMESPACE, type Onboarding} from '../model/useOnboarding';

/**
 * Step 6: the test opened in a new tab; the answers are polled until one arrives, then "processing" for 3 s and
 * "ready". "Skip the test" goes on without waiting.
 */
export function TestStep({onboarding}: {onboarding: Onboarding}) {
  const {t} = useTranslation(NAMESPACE);
  const {state, dispatch, publicUrl} = onboarding;
  const status = useAnswerWatch(state.questionnaireId, true);
  const testUrl = `${publicUrl(state.slug)}?test=1`;

  return (
    <section className="onboarding__section" aria-labelledby="onb-test">
      <h1 id="onb-test" className="serif-heading onboarding__title">
        {t('test.title')}
      </h1>
      <p className="muted">{t('test.subtitle')}</p>
      <div className="card onboarding__card stack">
        <p
          className={`onboarding__status onboarding__status--${status}`}
          role="status"
          aria-label={t('test.status')}
        >
          {status === 'ready' ? <Icon name="check" /> : <Spinner size={18} />}
          <span>{t(`test.${status}`)}</span>
        </p>
        <div className="row">
          <a
            className="btn btn--secondary"
            href={testUrl}
            target="_blank"
            rel="noopener noreferrer"
          >
            <Icon name="external" />
            {t('test.open')}
          </a>
        </div>
      </div>
      <div className="onboarding__actions">
        {status === 'ready' ? (
          <Button
            variant="primary"
            onClick={() => dispatch({type: 'goTo', step: 7})}
          >
            {t('actions.continue')}
          </Button>
        ) : (
          <Button
            variant="ghost"
            onClick={() => dispatch({type: 'goTo', step: 7})}
          >
            {t('test.skip')}
          </Button>
        )}
      </div>
    </section>
  );
}
