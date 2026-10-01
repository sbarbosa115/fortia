import {ChatPanel} from '@console/widgets/chat-panel';
import {Tabs} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {ProjectWizardState} from '../model/useProjectWizard';
import type {QuestionSource} from '../model/wizard';
import {ExistingQuestionnaire} from './ExistingQuestionnaire';

/**
 * Step 1 (PRD §10.12): the questions come from the chat in draft mode — it drafts, the user asks for changes, and
 * approving the draft saves the questionnaire — or from a questionnaire the account already has.
 */
export function QuestionsStep({wizard}: {wizard: ProjectWizardState}) {
  const {t} = useTranslation('pages.project-new');
  const copy = wizard.approved
    ? 'chatDone'
    : wizard.draft
      ? 'chatChanges'
      : 'chat';

  return (
    <div className="prj-new__stack">
      <div className="prj-new__tabs">
        <Tabs<QuestionSource>
          label={t('questions.sourceLabel')}
          tabs={[
            {key: 'chat', label: t('questions.sourceChat')},
            {key: 'existing', label: t('questions.sourceExisting')},
          ]}
          active={wizard.source}
          onChange={wizard.chooseSource}
        />
      </div>
      {wizard.source === 'chat' ? (
        <section className="prj-new__card" aria-labelledby="prj-new-chat-title">
          <div className="prj-new__card-head">
            <h2 id="prj-new-chat-title" className="prj-new__card-title">
              {t(`questions.${copy}Title`)}
            </h2>
            <p className="prj-new__small-muted">{t(`questions.${copy}Hint`)}</p>
          </div>
          <ChatPanel
            chat={wizard.chat}
            greeting={t('questions.greeting')}
            disabledReason={wizard.chatReason}
          />
        </section>
      ) : (
        <section
          className="prj-new__card"
          aria-labelledby="prj-new-existing-title"
        >
          <div className="prj-new__card-head">
            <h2 id="prj-new-existing-title" className="prj-new__card-title">
              {t('questions.existingTitle')}
            </h2>
            <p className="prj-new__small-muted">
              {t('questions.existingHint')}
            </p>
          </div>
          <ExistingQuestionnaire wizard={wizard} />
        </section>
      )}
    </div>
  );
}
