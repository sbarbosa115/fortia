import {Badge, Button} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {NAMESPACE, type Onboarding} from '../model/useOnboarding';

/** Step 3: the template recommended for the goal. "Start from scratch →" finishes onboarding. */
export function TemplateStep({onboarding}: {onboarding: Onboarding}) {
  const {t} = useTranslation(NAMESPACE);
  const {preview, dispatch, finish, finishing} = onboarding;

  return (
    <section className="onboarding__section" aria-labelledby="onb-template">
      <h1 id="onb-template" className="serif-heading onboarding__title">
        {t('template.title')}
      </h1>
      <p className="muted">{t('template.subtitle')}</p>
      {preview ? (
        <article className="card onboarding__card onboarding__template">
          <div className="row">
            <Badge tone="accent">{t('template.recommended')}</Badge>
            <span className="muted">
              {t('template.questions', {count: preview.questions.length})}
            </span>
          </div>
          <h2 className="onboarding__template-title">{preview.title}</h2>
          <p className="muted">{preview.description}</p>
          <ol className="onboarding__question-list">
            {preview.questions.map((question) => (
              <li key={question.key}>{question.title}</li>
            ))}
          </ol>
        </article>
      ) : null}
      <div className="onboarding__actions">
        <Button onClick={() => dispatch({type: 'goTo', step: 2})}>
          {t('actions.back')}
        </Button>
        <Button
          variant="ghost"
          loading={finishing === 'scratch'}
          disabled={finishing !== null}
          onClick={() => finish('scratch')}
        >
          {t('template.scratch')}
        </Button>
        <Button
          variant="primary"
          disabled={!preview}
          onClick={onboarding.applyTemplate}
        >
          {t('template.use')}
        </Button>
      </div>
    </section>
  );
}
