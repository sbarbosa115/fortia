import {
  closestCenter,
  DndContext,
  type DragEndEvent,
  KeyboardSensor,
  PointerSensor,
  TouchSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {CSS} from '@dnd-kit/utilities';
import {visibleOptions, type VisibleOption} from '@respondent/entities/session';
import {Icon, IconButton} from '@shared/ui';
import {useEffect} from 'react';
import {useTranslation} from 'react-i18next';
import type {ControlProps} from '../model/types';

/** The order shown: the saved one (unknown values dropped, new ones appended), else the options' order. */
export function rankingOrder(
  options: VisibleOption[],
  value: unknown,
): VisibleOption[] {
  if (!Array.isArray(value)) {
    return options;
  }
  const byValue = new Map(options.map((option) => [option.value, option]));
  const ordered = value
    .map((item) => byValue.get(String(item)))
    .filter((option): option is VisibleOption => option !== undefined);
  return [...ordered, ...options.filter((option) => !ordered.includes(option))];
}

function RankingItem({
  option,
  position,
  count,
  disabled,
  onMove,
}: {
  option: VisibleOption;
  position: number;
  count: number;
  disabled: boolean;
  onMove: (from: number, to: number) => void;
}) {
  const {t} = useTranslation('features.answer-question');
  const {attributes, listeners, setNodeRef, transform, transition, isDragging} =
    useSortable({id: option.value, disabled});
  return (
    <li
      ref={setNodeRef}
      className="answer-rank"
      data-dragging={isDragging || undefined}
      style={{transform: CSS.Transform.toString(transform), transition}}
    >
      <span className="answer-rank__number" aria-hidden="true">
        {position + 1}
      </span>
      <button
        type="button"
        className="answer-rank__handle"
        disabled={disabled}
        aria-label={t('ranking.drag', {label: option.label})}
        {...attributes}
        {...listeners}
      >
        <Icon name="grip" />
      </button>
      <span className="answer-rank__label">{option.label}</span>
      <span className="answer-rank__moves">
        <IconButton
          size="sm"
          label={t('ranking.up', {label: option.label})}
          icon={<Icon name="chevron-up" />}
          disabled={disabled || position === 0}
          onClick={() => onMove(position, position - 1)}
        />
        <IconButton
          size="sm"
          label={t('ranking.down', {label: option.label})}
          icon={<Icon name="chevron-down" />}
          disabled={disabled || position === count - 1}
          onClick={() => onMove(position, position + 1)}
        />
      </span>
    </li>
  );
}

/**
 * Ranking: reorderable with mouse, touch and keyboard, numbered 1 to n. The initial order is already a valid answer
 * and is saved at once; the value is the values in order (PRD §9.4 ranking).
 */
export function RankingControl({
  question,
  control,
  gender,
  disabled,
  onChange,
}: ControlProps) {
  const {t} = useTranslation('features.answer-question');
  const order = rankingOrder(visibleOptions(control, gender), control.value);
  const values = order.map((option) => option.value);
  const unsaved = !Array.isArray(control.value);
  const sensors = useSensors(
    useSensor(PointerSensor, {activationConstraint: {distance: 4}}),
    useSensor(TouchSensor, {activationConstraint: {delay: 120, tolerance: 6}}),
    useSensor(KeyboardSensor, {coordinateGetter: sortableKeyboardCoordinates}),
  );

  useEffect(() => {
    if (unsaved && !disabled) {
      onChange(values);
    }
    // Only when the question opens without a saved order.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [unsaved, disabled]);

  const move = (from: number, to: number) => {
    if (to < 0 || to >= values.length || from === to) {
      return;
    }
    onChange(arrayMove(values, from, to));
  };
  const onDragEnd = ({active, over}: DragEndEvent) => {
    if (over && active.id !== over.id) {
      move(values.indexOf(String(active.id)), values.indexOf(String(over.id)));
    }
  };

  return (
    <div className="answer-ranking">
      <p className="answer-hint">{t('ranking.hint')}</p>
      <DndContext
        sensors={sensors}
        collisionDetection={closestCenter}
        onDragEnd={onDragEnd}
      >
        <SortableContext items={values} strategy={verticalListSortingStrategy}>
          <ol className="answer-ranks" aria-label={question.title}>
            {order.map((option, position) => (
              <RankingItem
                key={option.value}
                option={option}
                position={position}
                count={order.length}
                disabled={disabled}
                onMove={move}
              />
            ))}
          </ol>
        </SortableContext>
      </DndContext>
    </div>
  );
}
