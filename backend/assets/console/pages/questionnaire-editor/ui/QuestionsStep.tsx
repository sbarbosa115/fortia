import {useTranslation} from 'react-i18next';
import {categoriesOf} from '../model/draft';
import {useEditorContext} from '../model/EditorContext';
import {maxScores} from '../model/scoring';
import {QuestionCard} from './QuestionCard';
import {StepProblem} from './StepProblem';

/**
 * Step 2 (PRD §10.5), as in the admin console: what still blocks the step (with "Show question"), then the question
 * picked in the outline. A diagnostic also shows its top score.
 */
export function QuestionsStep() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const {draft, selected} = editor;
  const problem = editor.issuesOf(2)[0];
  const scores =
    draft.kind === 'diagnostic' ? maxScores(draft.questions, draft.kind) : null;

  return (
    <>
      <datalist id="editor-categories">
        {categoriesOf(draft.questions)
          .filter((c) => c !== '')
          .map((c) => (
            <option key={c} value={c} />
          ))}
      </datalist>
      {scores ? (
        <p className="editor__score-line">
          <strong>{t('questions.maxScore', {max: scores.total})}</strong>
          {scores.byCategory.map((c) => (
            <span key={c.category} className="muted">
              {t('questions.categoryMax', {category: c.category, max: c.max})}
            </span>
          ))}
        </p>
      ) : null}
      {problem ? (
        <StepProblem
          message={t(problem.key, problem.params)}
          onShow={editor.problemKey ? editor.showProblem : undefined}
        />
      ) : null}
      {selected ? (
        <QuestionCard
          key={selected.key}
          question={selected}
          number={editor.selectedIndex + 1}
        />
      ) : null}
    </>
  );
}
