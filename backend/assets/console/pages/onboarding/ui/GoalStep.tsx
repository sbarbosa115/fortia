import {Badge, Button, ChoiceCards} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {GOALS, type Goal} from '../model/templates';
import {NAMESPACE, type Onboarding} from '../model/useOnboarding';
import {canLeaveGoal} from '../model/wizard';

/** Step 1: "What do you want to achieve with Mappi?" Continue stays disabled until a goal is chosen. */
export function GoalStep({onboarding}: {onboarding: Onboarding}) {
  const {t} = useTranslation(NAMESPACE);
  const {state, dispatch} = onboarding;

  return (
    <section className="onboarding__section" aria-labelledby="onb-goal">
      <h1 id="onb-goal" className="serif-heading onboarding__title">
        {t('goal.title')}
      </h1>
      <p className="muted">{t('goal.subtitle')}</p>
      <ChoiceCards<Goal>
        label={t('goal.label')}
        value={state.goal}
        onChange={(goal) => dispatch({type: 'chooseGoal', goal})}
        choices={GOALS.map((goal) => ({
          value: goal,
          title: t(`goal.options.${goal}.title`),
          body: t(`goal.options.${goal}.body`),
          extra: (
            <span className="onboarding__tag">
              <Badge tone="accent">{t(`goal.options.${goal}.tag`)}</Badge>
            </span>
          ),
        }))}
      />
      <div className="onboarding__actions">
        <Button
          variant="primary"
          disabled={!canLeaveGoal(state)}
          onClick={() => dispatch({type: 'goTo', step: 2})}
        >
          {t('actions.continue')}
        </Button>
      </div>
    </section>
  );
}
