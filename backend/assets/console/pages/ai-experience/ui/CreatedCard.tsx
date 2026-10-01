import {Button, Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {AiExperience} from '../model/useAiExperience';

/** "Questionnaire created": the assistant created everything the author confirmed. */
export function CreatedCard({chat}: {chat: AiExperience}) {
  const {t} = useTranslation('pages.ai-experience');

  return (
    <section className="ai-created">
      <span className="ai-created__icon">
        <Icon name="check-circle" size={24} />
      </span>
      <div className="ai-created__text">
        <p className="ai-created__title">{t('created.title')}</p>
        <p className="ai-created__name">{chat.createdTitle}</p>
      </div>
      <div className="ai-created__actions">
        {chat.viewUrl ? (
          <a
            className="btn btn--secondary"
            href={chat.viewUrl}
            target="_blank"
            rel="noopener noreferrer"
          >
            {t('created.view')}
            <Icon name="external" size={16} />
          </a>
        ) : null}
        <Button variant="primary" onClick={chat.editCreated}>
          {t('created.edit')}
          <Icon name="arrow-right" size={16} />
        </Button>
      </div>
    </section>
  );
}
