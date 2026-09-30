import {usePlanUsage} from '@console/entities/plan-usage';
import {useViewer} from '@console/entities/viewer';
import {useDocumentTitle} from '@shared/lib';
import {Badge, ChoiceCards, LoadingState} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';
import './questionnaire-new.css';

type CardKey = 'regular' | 'diagnostic' | 'quizFunnel' | 'chaining';

/** The four cards of PRD §10.5: the feature each needs and the editor each opens. */
const CARDS: {key: CardKey; feature: string; to: string}[] = [
  {key: 'regular', feature: 'regular', to: '/questionnaires/create/regular'},
  {
    key: 'diagnostic',
    feature: 'diagnostic',
    to: '/questionnaires/create/diagnostic',
  },
  {
    key: 'quizFunnel',
    feature: 'quiz-funnel',
    to: '/questionnaires/create/quizfunnel',
  },
  {key: 'chaining', feature: 'chain', to: '/questionnaires/create/chaining'},
];

/** /questionnaires/new: "What do you want to create?" A card the plan does not allow is disabled with its limit. */
export function QuestionnaireNewPage() {
  const {t} = useTranslation('pages.questionnaire-new');
  const viewer = useViewer();
  const usage = usePlanUsage(!viewer.isAdmin);
  const navigate = useNavigate();
  useDocumentTitle(`Mappi - ${t('title')}`);

  if (!viewer.isAdmin && usage.isPending) {
    return <LoadingState />;
  }

  const verdict = (feature: string) => {
    if (viewer.isAdmin || usage.isError || !usage.data) {
      return {allowed: true, reason: null};
    }
    const found = usage.data.features[feature];
    return {
      allowed: found?.allowed ?? false,
      reason: found?.reason ?? 'FEATURE_NOT_IN_PLAN',
    };
  };

  const choices = CARDS.map((card) => {
    const {allowed, reason} = verdict(card.feature);
    return {
      value: card.key,
      title: t(`cards.${card.key}.title`),
      body: t(`cards.${card.key}.body`),
      disabled: !allowed,
      extra: (
        <span className="questionnaire-new__extra">
          <Badge tone="accent">{t(`cards.${card.key}.label`)}</Badge>
          {allowed ? null : (
            <span className="questionnaire-new__limit">
              {t(`planLimit.${reason}`, {ns: 'shared'})}
            </span>
          )}
        </span>
      ),
    };
  });

  return (
    <div className="questionnaire-new">
      <h1 className="serif-heading questionnaire-new__title">{t('title')}</h1>
      <p className="muted">{t('subtitle')}</p>
      <ChoiceCards<CardKey>
        label={t('title')}
        value={null}
        choices={choices}
        onChange={(key) => {
          const card = CARDS.find((c) => c.key === key);
          if (card) {
            void navigate(card.to);
          }
        }}
      />
    </div>
  );
}
