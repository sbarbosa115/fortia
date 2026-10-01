import {publicFlowUrl} from '@shared/config';
import {slugify} from '@shared/lib';
import {Icon} from '@shared/ui';
import {type ReactNode, useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {isOptionType} from '../model/draft';
import {useEditorContext} from '../model/EditorContext';
import type {DraftQuestion} from '../model/types';
import {PREVIEW_MEDIA_QUERY} from '../model/useEditor';

type Device = 'mobile' | 'desktop';

/**
 * The right-hand live preview, as in the admin console (PRD §10.5): what the respondent sees for the current step,
 * in a phone or browser frame, with tabs where a step has several screens. Below 1100 px it is a drawer.
 */
export function Preview() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const {draft, step, previewTab, setPreviewTab, closePreview} = editor;
  const [device, setDevice] = useState<Device>('mobile');

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

  const url = decodeURI(
    new URL(
      publicFlowUrl(slugify(draft.slug.trim() || draft.title)),
      window.location.origin,
    ).toString(),
  ).replace(/^https?:\/\//, '');
  const capture = draft.kind === 'regular' ? draft.captureUserData : false;
  const tabs =
    step === 1 && draft.disclaimerOn
      ? [
          {id: 'cover', label: t('preview.tabCover')},
          {id: 'disclaimer', label: t('preview.tabDisclaimer')},
        ]
      : step === 3 && capture
        ? [
            {id: 'contact', label: t('preview.tabContact')},
            {id: 'final', label: t('preview.tabFinal')},
          ]
        : null;

  const first = draft.questions[0];
  const cover = draft.landingPage ? (
    <PreviewLanding />
  ) : first ? (
    <PreviewQuestion
      question={first}
      index={0}
      total={draft.questions.length}
    />
  ) : null;

  let content: ReactNode;
  if (step === 1) {
    content =
      draft.disclaimerOn && previewTab === 'disclaimer' ? (
        <PreviewDisclaimer>{cover}</PreviewDisclaimer>
      ) : (
        cover
      );
  } else if (step === 2) {
    const index = editor.selectedIndex;
    const select = (i: number) => {
      const question = draft.questions[i];
      if (question) {
        editor.selectQuestion(question.key);
      }
    };
    content = editor.selected ? (
      <PreviewQuestion
        question={editor.selected}
        index={index}
        total={draft.questions.length}
        onBack={() => select(index - 1)}
        onNext={() => select(index + 1)}
      />
    ) : null;
  } else {
    content =
      capture && previewTab === 'contact' ? (
        <PreviewContact onContinue={() => setPreviewTab('final')} />
      ) : (
        <PreviewFinal />
      );
  }

  return (
    <>
      <div className="preview-backdrop" aria-hidden onClick={closePreview} />
      <aside className="preview" aria-label={t('preview.live')}>
        <div className="preview__bar">
          <span className="preview__live">
            <span className="preview__dot" aria-hidden />
            {t('preview.live')}
          </span>
          <div className="preview__controls">
            <div className="segmented">
              {(['mobile', 'desktop'] as const).map((d) => (
                <button
                  key={d}
                  type="button"
                  className="segmented__item"
                  aria-pressed={device === d}
                  aria-label={t(`preview.${d}`)}
                  title={t(`preview.${d}`)}
                  onClick={() => setDevice(d)}
                >
                  <Icon
                    name={d === 'mobile' ? 'smartphone' : 'monitor'}
                    size={14}
                  />
                </button>
              ))}
            </div>
            <button
              type="button"
              className="preview__close"
              aria-label={t('preview.close')}
              title={t('preview.close')}
              onClick={closePreview}
            >
              <Icon name="close" size={16} />
            </button>
          </div>
        </div>
        {tabs ? (
          <div className="preview__tabs">
            <div
              className="segmented segmented--wide"
              role="tablist"
              aria-label={t('preview.screens')}
            >
              {tabs.map((tab) => (
                <button
                  key={tab.id}
                  type="button"
                  role="tab"
                  className="segmented__item"
                  aria-selected={tab.id === previewTab}
                  onClick={() => setPreviewTab(tab.id)}
                >
                  {tab.label}
                </button>
              ))}
            </div>
          </div>
        ) : null}
        <div className="preview__stage">
          <div
            className={`preview__frame preview__frame--${device}`}
            data-device={device}
          >
            {device === 'desktop' ? (
              <div className="preview__browser">
                <span className="preview__lights" aria-hidden>
                  <span />
                  <span />
                  <span />
                </span>
                <span className="preview__url">{url}</span>
              </div>
            ) : null}
            <div className="preview__screen">{content}</div>
          </div>
        </div>
      </aside>
    </>
  );
}

/** The welcome screen of the respondent app. */
function PreviewLanding() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const {draft} = useEditorContext();
  return (
    <div className="pv-landing">
      <div className="pv-landing__brand">
        <span aria-hidden>{'Q'}</span>
      </div>
      <div className="pv-landing__body">
        <span className="pv-eyebrow pv-eyebrow--pill">
          {t('shopper.landingEyebrow')}
        </span>
        <h3 className="pv-landing__title">
          {draft.title.trim() || t('shopper.untitled')}
        </h3>
        <p className="pv-muted">
          {draft.description.trim() || t('shopper.descriptionPlaceholder')}
        </p>
        <span className="pv-button">
          {t('shopper.landingCta')}
          <Icon name="arrow-right" size={14} />
        </span>
        <span className="pv-small">
          {t('shopper.questionCount', {count: draft.questions.length})}
        </span>
      </div>
    </div>
  );
}

/** The disclaimer the respondent must accept: a bottom sheet over the first screen. */
function PreviewDisclaimer({children}: {children: ReactNode}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const {draft} = useEditorContext();
  return (
    <div className="pv-sheet">
      <div className="pv-sheet__under" aria-hidden>
        {children}
      </div>
      <div className="pv-sheet__overlay">
        <div className="pv-sheet__panel">
          <div className="pv-sheet__top">
            <span>{t('shopper.disclaimerBrand')}</span>
            <span className="pv-sheet__badge">
              <Icon name="lock" size={12} />
              {t('shopper.disclaimerBadge')}
            </span>
          </div>
          <h3 className="pv-title">{t('shopper.disclaimerTitle')}</h3>
          <p className="pv-muted pv-pre">
            {draft.disclaimer.trim() || t('shopper.disclaimerPlaceholder')}
          </p>
          <span className="pv-button pv-button--block">
            <Icon name="check" size={14} />
            {t('shopper.disclaimerYes')}
          </span>
          <span className="pv-sheet__no">{t('shopper.disclaimerNo')}</span>
        </div>
      </div>
    </div>
  );
}

const MULTI = ['checkbox', 'selection_with_score'];

/** The question screen of the respondent app: progress, the answer control of its type, Back and Next. */
function PreviewQuestion({
  question,
  index,
  total,
  onBack,
  onNext,
}: {
  question: DraftQuestion;
  index: number;
  total: number;
  onBack?: () => void;
  onNext?: () => void;
}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const percent = total > 0 ? Math.round(((index + 1) / total) * 100) : 0;
  const isLast = index >= total - 1;
  const label = (text: string, i: number) =>
    text.trim() || t('shopper.choiceN', {n: i + 1});
  const type = question.type;

  let control: ReactNode = null;
  if (isOptionType(type) && type !== 'select' && type !== 'ranking') {
    control = question.options.map((option, i) => (
      <span key={option.key} className="pv-choice">
        <span
          className={
            MULTI.includes(type) ? 'pv-mark pv-mark--square' : 'pv-mark'
          }
          aria-hidden
        />
        {label(option.label, i)}
      </span>
    ));
  } else if (type === 'select') {
    control = (
      <span className="pv-choice pv-choice--select">
        {t('shopper.selectPlaceholder')}
        <Icon name="chevron-down" size={16} />
      </span>
    );
  } else if (type === 'ranking') {
    control = (
      <>
        <p className="pv-small">{t('shopper.rankingHint')}</p>
        {question.options.map((option, i) => (
          <span key={option.key} className="pv-choice">
            <strong className="pv-rank">{i + 1}</strong>
            <span className="pv-grow">{label(option.label, i)}</span>
            <Icon name="grip" size={16} />
          </span>
        ))}
      </>
    );
  } else if (type === 'range') {
    control = (
      <div className="pv-range">
        <div className="pv-range__track">
          <span />
        </div>
        <div className="pv-range__ends">
          <span>{question.rangeMin || '0'}</span>
          <span>{question.rangeMax || '10'}</span>
        </div>
      </div>
    );
  } else if (type === 'text') {
    control = (
      <>
        <span className="pv-textarea">{t('shopper.textPlaceholder')}</span>
        {question.maxFollowups > 0 ? (
          <p className="pv-small pv-hint">
            <Icon name="sparkles" size={14} />
            {t('shopper.followupHint')}
          </p>
        ) : null}
      </>
    );
  } else if (type === 'audio' || type === 'file') {
    control = (
      <span className="pv-drop">
        <Icon name={type === 'audio' ? 'mic' : 'upload'} size={16} />
        {t(type === 'audio' ? 'shopper.audio' : 'shopper.file')}
      </span>
    );
  } else if (type === 'table') {
    control = (
      <div className="pv-table">
        {question.options.map((option, i) => (
          <span key={option.key}>{label(option.label, i)}</span>
        ))}
      </div>
    );
  } else if (type === 'message') {
    control = <p className="pv-small">{t('shopper.message')}</p>;
  }

  return (
    <div className="pv-question">
      <div className="pv-progress__label">
        <span>{t('shopper.questionOf', {n: index + 1, total})}</span>
        <span>{`${percent}%`}</span>
      </div>
      <div
        className="pv-progress"
        role="progressbar"
        aria-valuenow={percent}
        aria-valuemin={0}
        aria-valuemax={100}
      >
        <span style={{width: `${percent}%`}} />
      </div>
      <h3 className="pv-title pv-title--question">
        {question.title.trim() || t('shopper.untitledQuestion')}
      </h3>
      {question.description.trim() ? (
        <p className="pv-muted">{question.description}</p>
      ) : null}
      <div className="pv-answers">{control}</div>
      <div className="pv-nav">
        <button
          type="button"
          className="pv-nav__back"
          disabled={!onBack || index === 0}
          onClick={onBack}
        >
          <Icon name="arrow-left" size={14} />
          {t('shopper.back')}
        </button>
        <div className="pv-nav__right">
          {!question.required ? (
            <span className="pv-nav__skip">{t('shopper.skip')}</span>
          ) : null}
          <button
            type="button"
            className="pv-button"
            disabled={!onNext || isLast}
            onClick={onNext}
          >
            {t(isLast ? 'shopper.finish' : 'shopper.next')}
            <Icon name="arrow-right" size={14} />
          </button>
        </div>
      </div>
    </div>
  );
}

/** The contact form asked before the end. */
function PreviewContact({onContinue}: {onContinue: () => void}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  return (
    <div className="pv-contact">
      <span className="pv-eyebrow">{t('shopper.contactEyebrow')}</span>
      <h3 className="pv-title">{t('shopper.contactTitle')}</h3>
      <p className="pv-muted">{t('shopper.contactDescription')}</p>
      <div className="pv-contact__fields">
        {(['name', 'email', 'phone'] as const).map((field) => (
          <div key={field}>
            <span className="pv-contact__label">
              {t(`shopper.contactFields.${field}`)}
            </span>
            <span className="pv-contact__input">
              {t(`shopper.contactPlaceholders.${field}`)}
            </span>
          </div>
        ))}
      </div>
      <button
        type="button"
        className="pv-button pv-button--block"
        onClick={onContinue}
      >
        {t('shopper.contactCta')}
        <Icon name="arrow-right" size={14} />
      </button>
      <p className="pv-small pv-hint">
        <Icon name="shield-check" size={14} />
        {t('shopper.contactPrivacy')}
      </p>
    </div>
  );
}

/** The end of the questionnaire: the closing message and the call to action. */
function PreviewFinal() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const {draft} = useEditorContext();
  const thankYou = draft.kind === 'regular' && draft.thankYouOn;
  const ctaOn = draft.kind === 'diagnostic' ? draft.blocks.cta : draft.ctaOn;
  const {cta} = draft;
  return (
    <div className="pv-final">
      <section className="pv-final__card">
        <span className="pv-final__icon" aria-hidden>
          <Icon name="check-circle" size={20} />
        </span>
        <h3 className="pv-title">
          {(thankYou && draft.thankYouTitle.trim()) ||
            t('shopper.defaultEndTitle')}
        </h3>
        <p className="pv-muted pv-pre">
          {(thankYou && draft.thankYouMessage.trim()) ||
            t('shopper.defaultEndMessage')}
        </p>
      </section>
      {ctaOn ? (
        <section className="pv-cta">
          <p className="pv-cta__title">
            {cta.title || t('cta.titlePlaceholder')}
          </p>
          {cta.description ? (
            <p className="pv-cta__description">{cta.description}</p>
          ) : null}
          <span className="pv-cta__button">
            {cta.buttonText || t('cta.buttonPlaceholder')}
            <Icon name="arrow-right" size={14} />
          </span>
        </section>
      ) : null}
    </div>
  );
}
