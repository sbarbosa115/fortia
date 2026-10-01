import {Button, Icon} from '@shared/ui';
import {useEffect, useRef} from 'react';
import {useTranslation} from 'react-i18next';
import {useAiExperience} from '../model/useAiExperience';
import {AssistantMark} from './AssistantMark';
import {ChatComposer} from './ChatComposer';
import {ChatTranscript} from './ChatTranscript';
import {CreatedCard} from './CreatedCard';
import {PreviewPanel} from './PreviewPanel';
import './ai-experience.css';

/**
 * AI Experience (PRD §10.4): a fullscreen conversation that builds the questionnaire, with the live preview of the
 * draft on the right while one is being built or edited; the rest of the time the chat has the whole width.
 */
export function AiExperiencePage() {
  const {t} = useTranslation('pages.ai-experience');
  const chat = useAiExperience();
  const scroller = useRef<HTMLDivElement>(null);
  const {messages, status} = chat;

  // Each turn scrolls the conversation (never the window) to its end.
  useEffect(() => {
    const box = scroller.current;
    box?.scrollTo?.({top: box.scrollHeight, behavior: 'smooth'});
  }, [messages, status]);

  return (
    <div className="ai-page">
      <div className="ai-page__chat">
        <div className="ai-page__toolbar">
          <Button
            variant="ghost"
            className="ai-tool"
            icon={<Icon name="arrow-left" size={16} />}
            onClick={chat.back}
          >
            {t('back')}
          </Button>
          <div className="ai-page__tools">
            <Button
              variant="ghost"
              className="ai-tool"
              icon={<Icon name="refresh" size={16} />}
              onClick={chat.newChat}
            >
              {t('newChat')}
            </Button>
            {chat.hasPreview ? (
              <button
                type="button"
                className="ai-preview-toggle"
                aria-pressed={chat.previewOpen}
                aria-label={t('preview.toggle')}
                title={t('preview.toggleHint')}
                onClick={chat.togglePreview}
              >
                <Icon name={chat.previewOpen ? 'eye' : 'eye-off'} size={16} />
              </button>
            ) : null}
          </div>
        </div>

        <div ref={scroller} className="ai-page__scroll">
          <div className="ai-page__column">
            {chat.messages.length === 0 ? (
              <div className="ai-hero">
                <span className="ai-hero__mark">
                  <AssistantMark size="lg" />
                </span>
                <p className="ai-hero__eyebrow">{t('eyebrow')}</p>
                <h1 className="ai-hero__title serif-heading">{t('title')}</h1>
                <p className="ai-hero__subtitle">{t('subtitle')}</p>
              </div>
            ) : null}
            <ChatTranscript chat={chat} />
            {chat.created ? <CreatedCard chat={chat} /> : null}
          </div>
        </div>

        <div className="ai-page__composer">
          <ChatComposer chat={chat} />
        </div>
      </div>

      {chat.previewOpen ? <PreviewPanel chat={chat} /> : null}
    </div>
  );
}
