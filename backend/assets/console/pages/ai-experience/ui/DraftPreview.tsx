import type {ChatDraft, ChatDraftQuestion} from '@console/entities/chat';
import {TagList} from '@console/entities/questionnaire';
import {joinClasses} from '@shared/lib';
import {Icon} from '@shared/ui';
import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {disclaimerOf} from '../model/preview';
import type {AiExperience} from '../model/useAiExperience';

type T = ReturnType<typeof useTranslation>['t'];

function Landing({
  draft,
  onStart,
  t,
}: {
  draft: ChatDraft;
  onStart?: () => void;
  t: T;
}) {
  const start = (
    <>
      {t('shopper.landingCta')}
      <Icon name="arrow-right" size={14} />
    </>
  );
  return (
    <div className="pv-landing">
      <div className="pv-landing__brand">
        <span aria-hidden="true">{'Q'}</span>
      </div>
      <div className="pv-landing__body">
        <span className="pv-eyebrow">{t('shopper.landingEyebrow')}</span>
        <h2 className="serif-heading">
          {draft.title?.trim() || t('shopper.untitled')}
        </h2>
        <p>
          {draft.description?.trim() || t('shopper.descriptionPlaceholder')}
        </p>
        {onStart ? (
          <button type="button" className="pv-button" onClick={onStart}>
            {start}
          </button>
        ) : (
          <span className="pv-button">{start}</span>
        )}
        <span className="pv-muted">
          {t('shopper.questionCount', {count: draft.questions.length})}
        </span>
      </div>
    </div>
  );
}

function Disclaimer({
  text,
  onAccept,
  children,
  t,
}: {
  text: string;
  onAccept: () => void;
  children: ReactNode;
  t: T;
}) {
  return (
    <div className="pv-disclaimer">
      <div aria-hidden="true" className="pv-disclaimer__under">
        {children}
      </div>
      <div className="pv-disclaimer__scrim">
        <div className="pv-disclaimer__sheet">
          <div className="pv-disclaimer__top">
            <span>{t('shopper.disclaimerBrand')}</span>
            <span className="pv-disclaimer__badge">
              <Icon name="lock" size={12} />
              {t('shopper.disclaimerBadge')}
            </span>
          </div>
          <h3>{t('shopper.disclaimerTitle')}</h3>
          <p>{text}</p>
          <button
            type="button"
            className="pv-button pv-button--block"
            onClick={onAccept}
          >
            <Icon name="check" size={14} />
            {t('shopper.disclaimerYes')}
          </button>
          <span className="pv-disclaimer__no">{t('shopper.disclaimerNo')}</span>
        </div>
      </div>
    </div>
  );
}

function Question({
  question,
  index,
  total,
  selected = [],
  onSelect,
  onBack,
  onNext,
  canGoBack = false,
  t,
}: {
  question: ChatDraftQuestion;
  index: number;
  total: number;
  selected?: string[];
  onSelect?: (value: string) => void;
  onBack?: () => void;
  onNext?: () => void;
  canGoBack?: boolean;
  t: T;
}) {
  const percent = total > 0 ? Math.round(((index + 1) / total) * 100) : 0;
  const isLast = index >= total - 1;
  const multi = question.type === 'checkbox';
  const min = question.min ?? 0;
  const max = question.max ?? 10;

  return (
    <div className="pv-question">
      <div className="pv-question__progress">
        <span>{t('shopper.questionOf', {n: index + 1, total})}</span>
        <span>{percent}%</span>
      </div>
      <div className="pv-bar">
        <div style={{width: `${percent}%`}} />
      </div>
      <h3>{question.title.trim() || t('shopper.untitledQuestion')}</h3>
      {question.description?.trim() ? (
        <p className="pv-muted">{question.description}</p>
      ) : null}

      <div className="pv-question__answers">
        {question.type === 'radio' || question.type === 'checkbox'
          ? question.choices.map((choice, i) => {
              const value =
                choice.value === null || choice.value === undefined
                  ? String(i)
                  : String(choice.value);
              const checked = selected.includes(value);
              return (
                <button
                  key={i}
                  type="button"
                  className="pv-choice"
                  aria-pressed={checked}
                  onClick={onSelect ? () => onSelect(value) : undefined}
                >
                  <span
                    aria-hidden="true"
                    className={joinClasses(
                      'pv-choice__mark',
                      multi && 'pv-choice__mark--box',
                    )}
                  />
                  {choice.label.trim() || t('shopper.choiceN', {n: i + 1})}
                </button>
              );
            })
          : null}
        {question.type === 'select' ? (
          <span className="pv-select">
            {t('shopper.selectPlaceholder')}
            <Icon name="chevron-down" size={16} />
          </span>
        ) : null}
        {question.type === 'range' ? (
          <div className="pv-range">
            <input
              type="range"
              min={min}
              max={max}
              value={selected[0] ?? String(Math.round((min + max) / 2))}
              onChange={(event) => onSelect?.(event.target.value)}
              aria-label={question.title}
            />
            <div className="pv-range__labels">
              <span>{min}</span>
              {selected[0] ? <strong>{selected[0]}</strong> : null}
              <span>{max}</span>
            </div>
          </div>
        ) : null}
        {question.type === 'text' ? (
          <span className="pv-text">{t('shopper.textPlaceholder')}</span>
        ) : null}
        {question.type === 'table' ? (
          <table className="pv-table">
            <thead>
              <tr>
                {(question.rows ?? []).length > 0 ? <td /> : null}
                {(question.columns ?? []).map((column, i) => (
                  <th key={i} scope="col">
                    {column}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {((question.rows ?? []).length > 0
                ? (question.rows ?? [])
                : ['']
              ).map((row, i) => (
                <tr key={i}>
                  {row ? <th scope="row">{row}</th> : null}
                  {(question.columns ?? []).map((_, j) => (
                    <td key={j}>
                      <span className="pv-table__cell" />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        ) : null}
        {question.type === 'file' ? (
          <div className="pv-file">
            {question.template ? (
              <span className="pv-file__template">
                <Icon name="download" size={14} />
                {t('shopper.template', {name: question.template.filename})}
              </span>
            ) : null}
            <span className="pv-text pv-file__drop">
              <Icon name="upload" size={16} />
              {t('shopper.filePlaceholder')}
            </span>
          </div>
        ) : null}
      </div>

      <div className="pv-question__nav">
        <button
          type="button"
          className="pv-link"
          onClick={onBack}
          disabled={!onBack || (index === 0 && !canGoBack)}
        >
          <Icon name="arrow-left" size={14} />
          {t('shopper.back')}
        </button>
        <div className="pv-question__next">
          {question.required === false ? (
            <span className="pv-link">{t('shopper.skip')}</span>
          ) : null}
          <button
            type="button"
            className="pv-button pv-button--sm"
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

function Final({draft, chat, t}: {draft: ChatDraft; chat: AiExperience; t: T}) {
  const tier =
    chat.activeTier === null ? undefined : chat.tiers[chat.activeTier];
  if (draft.type === 'diagnostic' && tier) {
    return (
      <div className="pv-result">
        <section className="pv-card">
          <span className="pv-result__eyebrow">
            {t('shopper.resultEyebrow')}
          </span>
          <h3>{t('shopper.resultTitle')}</h3>
          <p className="pv-muted">
            {draft.ending.message?.trim() || t('shopper.resultSubtitle')}
          </p>
          <div className="pv-result__tier">
            <span>{t('shopper.tierLabel')}</span>
            <p>{tier.name.trim() || t('preview.unnamedLevel')}</p>
            {tier.description?.trim() ? (
              <small>{tier.description}</small>
            ) : null}
          </div>
          {chat.score ? (
            <>
              <div className="pv-result__score">
                <span>{t('shopper.overallScore')}</span>
                <strong>
                  {chat.score.score}
                  <small>/{chat.score.max}</small>
                </strong>
              </div>
              <div className="pv-bar pv-bar--thick">
                <div
                  style={{
                    width: `${chat.score.max > 0 ? Math.round((chat.score.score / chat.score.max) * 100) : 0}%`,
                  }}
                />
              </div>
            </>
          ) : null}
        </section>
        {tier.recommendations.length > 0 ? (
          <section className="pv-card">
            <div className="pv-section__head">
              <span className="pv-section__icon">
                <Icon name="lightbulb" size={14} />
              </span>
              <h4>{t('shopper.recommendations')}</h4>
            </div>
            <ul className="pv-bullets">
              {tier.recommendations.map((text, i) => (
                <li key={i}>{text}</li>
              ))}
            </ul>
          </section>
        ) : null}
        {tier.action_plan.length > 0 ? (
          <section className="pv-card">
            <div className="pv-section__head">
              <span className="pv-section__icon">
                <Icon name="list-checks" size={14} />
              </span>
              <h4>{t('shopper.actionPlan')}</h4>
            </div>
            <ol className="pv-steps">
              {tier.action_plan.map((text, i) => (
                <li key={i}>
                  <span>{i + 1}</span>
                  {text}
                </li>
              ))}
            </ol>
          </section>
        ) : null}
      </div>
    );
  }
  return (
    <div className="pv-final">
      <section className="pv-card pv-final__card">
        <span className="pv-final__icon">
          <Icon name="check-circle" size={20} />
        </span>
        <h3>{t('shopper.defaultEndTitle')}</h3>
        <p className="pv-muted">
          {draft.ending.message?.trim() || t('shopper.defaultEndMessage')}
        </p>
      </section>
    </div>
  );
}

/**
 * The draft's tags (the owner's labels, never shown to respondents), above the preview; nothing without tags.
 */
export function DraftTags({chat}: {chat: AiExperience}) {
  const {t} = useTranslation('pages.ai-experience');
  const tags = chat.draft?.tags ?? [];
  if (tags.length === 0) {
    return null;
  }
  return (
    <div className="ai-preview__row ai-tags">
      <span className="ai-tags__label" aria-hidden="true">
        {t('preview.tags')}
      </span>
      <TagList tags={tags} label={t('preview.tags')} />
    </div>
  );
}

/** One screen of the chat's draft as the respondent will see it, walked with the screens' own buttons. */
export function DraftPreview({chat}: {chat: AiExperience}) {
  const {t} = useTranslation('pages.ai-experience');
  const {draft, screen} = chat;

  if (!draft || !screen) {
    return (
      <div className="pv-empty">
        <span className="pv-empty__icon">
          <Icon name="messages" size={20} />
        </span>
        <p className="pv-empty__title">{t('preview.emptyTitle')}</p>
        <p className="pv-muted">{t('preview.empty')}</p>
      </div>
    );
  }

  const first = draft.questions[0];
  const question =
    screen.kind === 'question' ? draft.questions[screen.index] : undefined;
  if (screen.kind === 'disclaimer') {
    // The sheet sits on what comes after it: the cover, the first question or nothing yet.
    return (
      <Disclaimer text={disclaimerOf(draft)} onAccept={chat.nextScreen} t={t}>
        {draft.landing_page === true ? (
          <Landing draft={draft} t={t} />
        ) : first ? (
          <Question
            question={first}
            index={0}
            total={draft.questions.length}
            t={t}
          />
        ) : null}
      </Disclaimer>
    );
  }

  if (screen.kind === 'cover') {
    return <Landing draft={draft} onStart={chat.nextScreen} t={t} />;
  }

  if (screen.kind === 'question') {
    return question ? (
      <Question
        question={question}
        index={screen.index}
        total={draft.questions.length}
        selected={chat.answers[screen.index] ?? []}
        onSelect={(value) => chat.answer(screen.index, value)}
        onBack={chat.previousScreen}
        onNext={chat.nextScreen}
        canGoBack={chat.canGoBack}
        t={t}
      />
    ) : null;
  }

  return (
    <div className="pv-end">
      <Final draft={draft} chat={chat} t={t} />
      {chat.canGoBack ? (
        <button
          type="button"
          className="pv-link pv-end__restart"
          onClick={chat.restartPreview}
        >
          <Icon name="rotate-ccw" size={14} />
          {t('preview.restart')}
        </button>
      ) : null}
    </div>
  );
}
