import {IconButton, Icon, Tabs} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {isOptionType} from '../model/draft';
import {useEditorContext} from '../model/EditorContext';
import type {DraftQuestion} from '../model/types';

type Device = 'mobile' | 'desktop';

/**
 * What the respondent sees, live, in a mobile or desktop frame (PRD §10.5). Below 1100 px it opens as a side panel.
 */
export function Preview() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const {draft} = editor;
  const [device, setDevice] = useState<Device>('mobile');
  const questions = draft.questions;
  return (
    <aside className="preview" aria-label={t('preview.title')}>
      <div className="preview__bar">
        <Tabs<Device>
          label={t('preview.device')}
          active={device}
          onChange={setDevice}
          tabs={[
            {key: 'mobile', label: t('preview.mobile')},
            {key: 'desktop', label: t('preview.desktop')},
          ]}
        />
        <IconButton
          size="sm"
          className="preview__close"
          label={t('preview.close')}
          icon={<Icon name="close" />}
          onClick={editor.closePreview}
        />
      </div>
      <div className={`preview__frame preview__frame--${device}`}>
        <div className="preview__screen">
          {draft.landingPage ? (
            <div className="preview__landing">
              <h3 className="serif-heading">
                {draft.title.trim() || t('preview.untitled')}
              </h3>
              {draft.description.trim() ? <p>{draft.description}</p> : null}
              {draft.disclaimerOn && draft.disclaimer.trim() ? (
                <p className="preview__disclaimer">{draft.disclaimer}</p>
              ) : null}
              <span className="preview__button">{t('preview.start')}</span>
            </div>
          ) : null}
          {questions.length === 0 ? (
            <p className="muted">{t('preview.empty')}</p>
          ) : (
            questions.map((question, i) => (
              <PreviewQuestion
                key={question.key}
                question={question}
                n={i + 1}
                total={questions.length}
              />
            ))
          )}
        </div>
      </div>
    </aside>
  );
}

function PreviewQuestion({
  question,
  n,
  total,
}: {
  question: DraftQuestion;
  n: number;
  total: number;
}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  return (
    <div className="preview__question">
      <span className="preview__count">
        {t('preview.questionOf', {n, total})}
      </span>
      <h4>{question.title.trim() || t('questions.titlePlaceholder')}</h4>
      {question.description.trim() ? (
        <p className="muted">{question.description}</p>
      ) : null}
      {isOptionType(question.type) && question.type !== 'select' ? (
        <ul className="preview__options">
          {question.options.map((option, i) => (
            <li key={option.key}>
              <span className="preview__letter">
                {String.fromCharCode(65 + (i % 26))}
              </span>
              {option.label || t('questions.optionLabel', {n: i + 1})}
            </li>
          ))}
        </ul>
      ) : null}
      {question.type === 'select' ? (
        <span className="preview__input">{t('preview.selectAnswer')}</span>
      ) : null}
      {question.type === 'text' ? (
        <span className="preview__input">{t('preview.textAnswer')}</span>
      ) : null}
      {question.type === 'audio' ? (
        <span className="preview__input">{t('preview.audioAnswer')}</span>
      ) : null}
      {question.type === 'file' ? (
        <span className="preview__input">{t('preview.fileAnswer')}</span>
      ) : null}
      {question.type === 'file' && question.template ? (
        <span className="preview__template">
          <Icon name="download" size={14} />
          {t('preview.template', {name: question.template.filename})}
        </span>
      ) : null}
      {question.type === 'table' ? (
        <table className="preview__table">
          <thead>
            <tr>
              {question.tableRows.some((row) => row.trim()) ? <td /> : null}
              {question.options.map((option, i) => (
                <th key={option.key} scope="col">
                  {option.label || t('questions.columnLabel', {n: i + 1})}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {(question.tableRows.some((row) => row.trim())
              ? question.tableRows.filter((row) => row.trim())
              : ['']
            ).map((row, i) => (
              <tr key={i}>
                {row ? <th scope="row">{row}</th> : null}
                {question.options.map((option) => (
                  <td key={option.key}>
                    <span className="preview__cell" />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      ) : null}
      {question.type === 'range' ? (
        <div className="preview__range" aria-hidden>
          <span>{question.rangeMin}</span>
          <span className="preview__track" />
          <span>{question.rangeMax}</span>
        </div>
      ) : null}
    </div>
  );
}
