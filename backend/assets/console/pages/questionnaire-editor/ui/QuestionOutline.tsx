import {Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {categoriesOf} from '../model/draft';
import {useEditorContext} from '../model/EditorContext';
import {maxScores} from '../model/scoring';

/**
 * The left panel of the Questions step, as in the admin console: every question (grouped by category in a
 * diagnostic), the selected one highlighted, a warning on the one with a problem, a trash on hover and "Add question".
 */
export function QuestionOutline() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const {draft} = editor;
  const scored = draft.kind === 'diagnostic';
  const canRemove = draft.questions.length > 1;
  const categories = scored ? categoriesOf(draft.questions) : [null];
  const byCategory = scored
    ? maxScores(draft.questions, draft.kind).byCategory
    : [];

  return (
    <div className="outline">
      <div className="outline__head">
        <h2>{t('questions.title')}</h2>
        <span className="outline__count">{draft.questions.length}</span>
      </div>
      <div className="outline__groups">
        {categories.map((category) => {
          const questions =
            category === null
              ? draft.questions
              : draft.questions.filter((q) => q.category.trim() === category);
          return (
            <div key={category ?? '-'}>
              {category !== null ? (
                <div className="outline__group">
                  <span
                    className={
                      category === ''
                        ? 'outline__group-name outline__group-name--missing'
                        : 'outline__group-name'
                    }
                  >
                    {category || t('questions.noCategory')}
                  </span>
                  <span className="outline__group-max">
                    {t('questions.categoryMaxShort', {
                      max:
                        byCategory.find((c) => c.category === category)?.max ??
                        0,
                    })}
                  </span>
                </div>
              ) : null}
              {category === '' ? (
                <p className="outline__warning">
                  {t('questions.assignCategory')}
                </p>
              ) : null}
              <ul className="outline__list">
                {questions.map((question) => {
                  const index = draft.questions.indexOf(question);
                  const selected = question.key === editor.selected?.key;
                  return (
                    <li key={question.key} className="outline__item">
                      <button
                        type="button"
                        className="outline__select"
                        aria-current={selected ? 'true' : undefined}
                        onClick={() => editor.selectQuestion(question.key)}
                      >
                        <span className="outline__number" aria-hidden>
                          {index + 1}
                        </span>
                        <span className="outline__text">
                          <span className="outline__title">
                            <span className="visually-hidden">
                              {`${t('questions.question', {n: index + 1})}: `}
                            </span>
                            {question.title.trim() || t('questions.untitled')}
                          </span>
                          <span className="outline__meta">
                            {t(`questions.types.${question.type}`)}
                          </span>
                        </span>
                        {question.key === editor.problemKey ? (
                          <span
                            className="outline__problem"
                            role="img"
                            aria-label={t('questions.hasProblem')}
                          >
                            <Icon name="alert" size={14} />
                          </span>
                        ) : null}
                      </button>
                      <button
                        type="button"
                        className="outline__remove"
                        disabled={!canRemove || editor.locked}
                        aria-label={t('questions.delete', {n: index + 1})}
                        title={
                          canRemove
                            ? t('questions.delete', {n: index + 1})
                            : t('questions.cannotRemove')
                        }
                        onClick={() => editor.deleteQuestion(question.key)}
                      >
                        <Icon name="trash" size={14} />
                      </button>
                    </li>
                  );
                })}
              </ul>
            </div>
          );
        })}
        {editor.locked ? null : (
          <button
            type="button"
            className="outline__add"
            onClick={() => editor.addQuestion()}
          >
            <Icon name="plus" size={14} />
            {t('questions.add')}
          </button>
        )}
      </div>
    </div>
  );
}
