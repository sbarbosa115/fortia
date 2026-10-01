import {useFileDrop} from '@console/features/chat-attachments';
import {joinClasses} from '@shared/lib';
import {Button, Icon} from '@shared/ui';
import {
  AssistantMark,
  ChatComposer,
  ChatTranscript,
} from '@console/widgets/chat-panel';
import {useEffect, useRef} from 'react';
import {useTranslation} from 'react-i18next';
import {useAiExperience} from '../model/useAiExperience';
import {CreatedCard} from './CreatedCard';
import {PreviewPanel} from './PreviewPanel';
import './ai-experience.css';

/**
 * AI Experience (PRD §10.4): a fullscreen conversation that builds the questionnaire, with the live preview of the
 * draft on the right while one is being built or edited; the rest of the time the chat has the whole width.
 */
export function AiExperiencePage() {
  const {t} = useTranslation('pages.ai-experience');
  const page = useAiExperience();
  const {chat} = page;
  const scroller = useRef<HTMLDivElement>(null);
  const {entries, status} = chat;
  // Documents dropped anywhere on the conversation attach to the next message.
  const {dragging, dropHandlers} = useFileDrop(
    chat.attachments,
    !chat.disabled,
  );

  // Each turn scrolls the conversation (never the window) to its end.
  useEffect(() => {
    const box = scroller.current;
    box?.scrollTo?.({top: box.scrollHeight, behavior: 'smooth'});
  }, [entries, status]);

  return (
    <div className="ai-page">
      <div
        className={joinClasses(
          'ai-page__chat',
          dragging && 'ai-page__chat--dragging',
        )}
        {...dropHandlers}
      >
        <div className="ai-page__toolbar">
          <Button
            variant="ghost"
            className="ai-tool"
            icon={<Icon name="arrow-left" size={16} />}
            onClick={page.back}
          >
            {t('back')}
          </Button>
          <div className="ai-page__tools">
            <Button
              variant="ghost"
              className="ai-tool"
              icon={<Icon name="refresh" size={16} />}
              onClick={page.newChat}
            >
              {t('newChat')}
            </Button>
            {page.hasPreview ? (
              <button
                type="button"
                className="ai-preview-toggle"
                aria-pressed={page.previewOpen}
                aria-label={t('preview.toggle')}
                title={t('preview.toggleHint')}
                onClick={page.togglePreview}
              >
                <Icon name={page.previewOpen ? 'eye' : 'eye-off'} size={16} />
              </button>
            ) : null}
          </div>
        </div>

        <div ref={scroller} className="ai-page__scroll">
          <div className="ai-page__column">
            {entries.length === 0 ? (
              <div className="ai-hero">
                <span className="ai-hero__mark">
                  <AssistantMark size="lg" />
                </span>
                <p className="ai-hero__eyebrow">{t('eyebrow')}</p>
                <h1 className="ai-hero__title serif-heading">{t('title')}</h1>
                <p className="ai-hero__subtitle">{t('subtitle')}</p>
              </div>
            ) : null}
            <ChatTranscript chat={chat} greeting={page.greeting} />
            {page.created ? <CreatedCard chat={page} /> : null}
          </div>
        </div>

        <div className="ai-page__composer">
          <ChatComposer chat={chat} autoFocus />
        </div>
      </div>

      {page.previewOpen ? <PreviewPanel chat={page} /> : null}
    </div>
  );
}
