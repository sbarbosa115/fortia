import {joinClasses, useDocumentTitle} from '@shared/lib';
import {Button, Icon, type IconName} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';
import './questionnaire-new.css';

type CardKey = 'regular' | 'diagnostic' | 'quizFunnel' | 'chaining';

/** The four cards of PRD §10.5: the editor each opens and its icons (as in the admin console). */
const CARDS: {
  key: CardKey;
  to: string;
  icon: IconName;
  tagIcon: IconName;
  isDefault?: boolean;
}[] = [
  {
    key: 'regular',
    to: '/questionnaires/create/regular',
    icon: 'clipboard-list',
    tagIcon: 'list',
    isDefault: true,
  },
  {
    key: 'diagnostic',
    to: '/questionnaires/create/diagnostic',
    icon: 'gauge',
    tagIcon: 'target',
  },
  {
    key: 'quizFunnel',
    to: '/questionnaires/create/quizfunnel',
    icon: 'filter',
    tagIcon: 'shopping-bag',
  },
  {
    key: 'chaining',
    to: '/questionnaires/create/chaining',
    icon: 'sparkles',
    tagIcon: 'wand',
  },
];

/**
 * /questionnaires/new: "What do you want to create?" Pick a card, then Continue opens its editor. The selection
 * starts on the first card.
 */
export function QuestionnaireNewPage() {
  const {t} = useTranslation('pages.questionnaire-new');
  const navigate = useNavigate();
  useDocumentTitle(`Mappi - ${t('title')}`);
  // undefined = not touched yet (the first card), null = cleared with Back.
  const [picked, setPicked] = useState<CardKey | null | undefined>(undefined);

  const selected = picked === undefined ? (CARDS[0]?.key ?? null) : picked;
  const selectedCard = CARDS.find((card) => card.key === selected);

  const handleContinue = () => {
    if (!selectedCard) {
      return;
    }
    void navigate(selectedCard.to);
  };

  return (
    <div className="questionnaire-new">
      <div className="questionnaire-new__inner">
        <div className="questionnaire-new__heading">
          <span className="questionnaire-new__eyebrow">{t('eyebrow')}</span>
          <h1 className="serif-heading questionnaire-new__title">
            {t('title')}
          </h1>
          <p className="questionnaire-new__subtitle">{t('subtitle')}</p>
        </div>

        <div
          className="questionnaire-new__cards"
          role="radiogroup"
          aria-label={t('title')}
        >
          {CARDS.map((card) => {
            const active = selected === card.key;
            return (
              <div key={card.key} className="questionnaire-new__slot">
                <button
                  type="button"
                  role="radio"
                  aria-checked={active}
                  onClick={() => setPicked(card.key)}
                  className={joinClasses(
                    'kind-card',
                    active && 'kind-card--active',
                  )}
                >
                  <span className="kind-card__radio" aria-hidden="true" />
                  <span className="kind-card__icon">
                    <Icon name={card.icon} size={20} />
                  </span>
                  <span className="kind-card__head">
                    <span className="kind-card__title">
                      {t(`cards.${card.key}.title`)}
                    </span>
                    {card.isDefault ? (
                      <span className="kind-card__default">
                        {t('defaultBadge')}
                      </span>
                    ) : null}
                  </span>
                  <span className="kind-card__body">
                    {t(`cards.${card.key}.body`)}
                  </span>
                  <span className="kind-card__tag">
                    <Icon name={card.tagIcon} size={14} />
                    {t(`cards.${card.key}.label`)}
                  </span>
                </button>
              </div>
            );
          })}
        </div>

        <div className="questionnaire-new__actions">
          <Button
            icon={<Icon name="arrow-left" size={16} />}
            onClick={() => setPicked(null)}
            disabled={selected === null}
          >
            {t('actions.back', {ns: 'shared'})}
          </Button>
          <Button
            variant="primary"
            className="questionnaire-new__continue"
            onClick={handleContinue}
            disabled={selected === null}
          >
            {t('actions.continue', {ns: 'shared'})}
            <Icon name="arrow-right" size={16} />
          </Button>
        </div>
      </div>
    </div>
  );
}
