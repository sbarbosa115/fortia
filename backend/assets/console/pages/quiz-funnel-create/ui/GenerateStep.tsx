import {Button, Card, CardBody, ChoiceCards, Icon, Spinner} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {Variant} from '../model/quizFunnel';
import type {QuizFunnelState} from '../model/useQuizFunnel';

/** Step 3 (PRD §10.5): "Design Experience" or "Profiling", then the generation job (polled up to 5 min). */
export function GenerateStep({state}: {state: QuizFunnelState}) {
  const {t} = useTranslation('pages.quiz-funnel-create');
  const count = state.products?.length ?? 0;
  return (
    <div className="quiz-funnel__step">
      <ChoiceCards<Variant>
        label={t('generate.typeLabel')}
        value={state.variant}
        onChange={state.setVariant}
        choices={[
          {
            value: 'experience',
            title: t('generate.experience.title'),
            body: t('generate.experience.body'),
          },
          {
            value: 'profiling',
            title: t('generate.profiling.title'),
            body: t('generate.profiling.body'),
          },
        ]}
      />
      <Card>
        <CardBody>
          <div className="quiz-funnel__generate">
            <p className="quiz-funnel__summary">
              {count > 0
                ? t('generate.withProducts', {count})
                : state.source === 'website'
                  ? t('generate.willScrape')
                  : t('generate.withStored')}
            </p>
            {state.generateError ? (
              <p className="quiz-funnel__error" role="alert">
                {state.generateError}
              </p>
            ) : null}
            {state.generating ? (
              <div className="quiz-funnel__loading" role="status">
                <Spinner />
                <p>{t('generate.working')}</p>
                <p className="muted">{t(state.loadingMessage)}</p>
              </div>
            ) : null}
            <div className="row">
              <Button
                variant="primary"
                icon={<Icon name="sparkles" size={16} />}
                loading={state.generating}
                onClick={state.generate}
              >
                {t('generate.submit')}
              </Button>
            </div>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
