import {
  closestCenter,
  DndContext,
  type DragEndEvent,
  KeyboardSensor,
  PointerSensor,
  useDroppable,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  SortableContext,
  sortableKeyboardCoordinates,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {Button, Icon} from '@shared/ui';
import {useId} from 'react';
import {useTranslation} from 'react-i18next';
import {categoriesOf} from '../model/draft';
import {useEditorContext} from '../model/EditorContext';
import {maxScores} from '../model/scoring';
import {QuestionCard} from './QuestionCard';

const GROUP_PREFIX = 'category:';

/**
 * Step 2 (PRD §10.5): the questions grouped by category. Drag to reorder; dropping onto another category moves the
 * question there. Add, duplicate and delete.
 */
export function QuestionsStep() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const headingId = useId();
  const editor = useEditorContext();
  const {draft} = editor;
  const sensors = useSensors(
    useSensor(PointerSensor, {activationConstraint: {distance: 6}}),
    useSensor(KeyboardSensor, {coordinateGetter: sortableKeyboardCoordinates}),
  );
  const categories = categoriesOf(draft.questions);
  const grouped = categories.length > 1 || (categories[0] ?? '') !== '';
  const scores =
    draft.kind === 'diagnostic' ? maxScores(draft.questions, draft.kind) : null;

  const onDragEnd = ({active, over}: DragEndEvent) => {
    if (!over || active.id === over.id) {
      return;
    }
    const overId = String(over.id);
    editor.moveQuestion(
      String(active.id),
      overId.startsWith(GROUP_PREFIX)
        ? {category: overId.slice(GROUP_PREFIX.length)}
        : {overKey: overId},
    );
  };

  return (
    <section className="editor__step" aria-labelledby={headingId}>
      <h2 id={headingId} className="editor__step-title">
        {t('questions.title')}
      </h2>
      <p className="muted">{t('questions.subtitle')}</p>
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
      <datalist id="editor-categories">
        {categories
          .filter((c) => c !== '')
          .map((c) => (
            <option key={c} value={c} />
          ))}
      </datalist>
      <DndContext
        sensors={sensors}
        collisionDetection={closestCenter}
        onDragEnd={onDragEnd}
      >
        {categories.map((category) => (
          <CategoryGroup
            key={category || '-'}
            category={category}
            showHeader={grouped}
          />
        ))}
      </DndContext>
      <div className="row">
        <Button icon={<Icon name="plus" />} onClick={() => editor.addQuestion()}>
          {t('questions.add')}
        </Button>
      </div>
    </section>
  );
}

function CategoryGroup({
  category,
  showHeader,
}: {
  category: string;
  showHeader: boolean;
}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const {setNodeRef, isOver} = useDroppable({id: `${GROUP_PREFIX}${category}`});
  const questions = editor.draft.questions.filter(
    (q) => q.category.trim() === category,
  );
  const name = category || t('questions.noCategory');
  return (
    <div
      ref={setNodeRef}
      className={isOver ? 'editor__group editor__group--over' : 'editor__group'}
    >
      {showHeader ? (
        <div className="editor__group-header">
          <h3>{name}</h3>
          <Button
            size="sm"
            variant="ghost"
            icon={<Icon name="plus" />}
            onClick={() => editor.addQuestion(category)}
          >
            {t('questions.addTo', {category: name})}
          </Button>
        </div>
      ) : null}
      <SortableContext
        items={questions.map((q) => q.key)}
        strategy={verticalListSortingStrategy}
      >
        <div className="stack">
          {questions.map((question) => (
            <QuestionCard
              key={question.key}
              question={question}
              number={editor.draft.questions.indexOf(question) + 1}
            />
          ))}
        </div>
      </SortableContext>
    </div>
  );
}
