import {joinClasses} from '@shared/lib';
import {Icon, IconButton} from '@shared/ui';
import {useEffect} from 'react';
import {useTranslation} from 'react-i18next';
import {tierColor} from '../model/preview';
import {type AiExperience, PREVIEW_MEDIA_QUERY} from '../model/useAiExperience';
import {DraftPreview, DraftTags} from './DraftPreview';

/** The right-hand live preview: what the respondent will see, in a phone or browser frame. */
export function PreviewPanel({chat}: {chat: AiExperience}) {
  const {t} = useTranslation('pages.ai-experience');
  const {device, closePreview} = chat;

  // As a drawer it covers the header's toggle, so Escape closes it too.
  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (
        event.key === 'Escape' &&
        !window.matchMedia?.(PREVIEW_MEDIA_QUERY).matches
      ) {
        closePreview();
      }
    };
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [closePreview]);

  return (
    <>
      <div
        className="ai-preview__scrim"
        aria-hidden="true"
        onClick={closePreview}
      />
      <aside className="ai-preview" aria-label={t('preview.live')}>
        <div className="ai-preview__bar">
          <span className="ai-preview__live">
            <span aria-hidden="true" className="ai-preview__dot" />
            {t('preview.live')}
          </span>
          <div className="ai-preview__controls">
            <div className="ai-segment">
              {(['mobile', 'desktop'] as const).map((option) => (
                <button
                  key={option}
                  type="button"
                  className="ai-segment__item"
                  aria-pressed={device === option}
                  aria-label={t(`preview.${option}`)}
                  title={t(`preview.${option}`)}
                  onClick={() => chat.setDevice(option)}
                >
                  <Icon
                    name={option === 'mobile' ? 'smartphone' : 'monitor'}
                    size={14}
                  />
                </button>
              ))}
            </div>
            <IconButton
              size="sm"
              className="ai-preview__close"
              label={t('preview.close')}
              icon={<Icon name="close" size={16} />}
              onClick={closePreview}
            />
          </div>
        </div>

        {chat.previewTabs.length > 1 ? (
          <div className="ai-preview__row">
            <div
              className="ai-segment ai-segment--wide"
              role="tablist"
              aria-label={t('preview.screens')}
            >
              {chat.previewTabs.map((tab) => (
                <button
                  key={tab.id}
                  type="button"
                  role="tab"
                  className="ai-segment__item"
                  aria-selected={tab.id === chat.activeTab}
                  onClick={() => chat.goToTab(tab.id)}
                >
                  {tab.label}
                </button>
              ))}
            </div>
          </div>
        ) : null}

        <DraftTags chat={chat} />

        {chat.showTierChips ? (
          <div
            className="ai-preview__row ai-tiers"
            role="group"
            aria-label={t('preview.viewAs')}
          >
            <span className="ai-tiers__label">{t('preview.viewAs')}</span>
            {chat.tiers.map((tier, index) => (
              <button
                key={index}
                type="button"
                className="ai-tiers__chip"
                aria-pressed={index === chat.activeTier}
                onClick={() => chat.pickTier(index)}
              >
                <span
                  aria-hidden="true"
                  className="ai-tiers__dot"
                  style={{backgroundColor: tierColor(index)}}
                />
                {tier.name.trim() || t('preview.levelN', {n: index + 1})}
              </button>
            ))}
          </div>
        ) : null}

        <div className="ai-preview__stage">
          <div
            data-testid="preview-frame"
            data-device={device}
            className={joinClasses('ai-frame', `ai-frame--${device}`)}
          >
            {device === 'desktop' ? (
              <div className="ai-frame__chrome">
                <span aria-hidden="true" className="ai-frame__lights">
                  <span />
                  <span />
                  <span />
                </span>
                <span className="ai-frame__url">{t('preview.url')}</span>
              </div>
            ) : null}
            <div className="ai-frame__screen">
              <DraftPreview chat={chat} />
            </div>
          </div>
        </div>
      </aside>
    </>
  );
}
