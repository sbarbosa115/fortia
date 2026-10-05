import {TagList} from '@console/entities/questionnaire';
import {Button, EmptyState, ErrorState, Icon, Select, Toggle} from '@shared/ui';
import {useMemo} from 'react';
import type {UIEvent} from 'react';
import {useTranslation} from 'react-i18next';
import {
  CHOICE_SORTS,
  type ChoiceRow,
  type ChoiceSort,
} from '../model/questionnaireFilter';
import type {AssignationWizardState} from '../model/useAssignationWizard';
import {useQuestionnaireChoices} from '../model/useQuestionnaireChoices';
import type {PickedQuestionnaire} from '../model/wizard';
import {Highlight} from './Highlight';
import {TagFilter} from './TagFilter';

function toPicked(row: ChoiceRow): PickedQuestionnaire {
  return {
    id: row.questionnaire_id,
    title: row.title,
    questionCount: row.question_count,
  };
}

/**
 * Step 1: the questionnaires the organization will answer — one checkbox each. Searched by name or tag, filtered by
 * any number of tags (a questionnaire with ANY of them shows), sorted, and narrowed to the picked ones; the picked
 * ones stay picked whatever the filters. "Select the visible ones" picks every row the filters leave. A questionnaire
 * without questions can't be picked.
 */
export function QuestionnairesStep({wizard}: {wizard: AssignationWizardState}) {
  const {t} = useTranslation('pages.assignation-form');
  const picked = useMemo(
    () => new Set(wizard.questionnaires.map((q) => q.id)),
    [wizard.questionnaires],
  );
  const choices = useQuestionnaireChoices(picked);
  const tagNames = new Map(choices.tagCounts.map((tag) => [tag.key, tag.tag]));
  const selectable = choices.shown.filter((row) => row.question_count > 0);
  const allVisiblePicked =
    selectable.length > 0 &&
    selectable.every((row) => picked.has(row.questionnaire_id));
  const onScroll = (event: UIEvent<HTMLDivElement>) => {
    const box = event.currentTarget;
    if (box.scrollTop + box.clientHeight >= box.scrollHeight - 80) {
      choices.drawMore();
    }
  };

  return (
    <section className="asg-wiz__card" aria-labelledby="asg-wiz-questionnaires">
      <div className="asg-wiz__card-head">
        <h2 id="asg-wiz-questionnaires" className="asg-wiz__card-title">
          {t('questionnaires.question')}
        </h2>
        {wizard.questionnaires.length > 0 ? (
          <p className="asg-wiz__chosen">
            <span className="asg-wiz__chosen-count">
              {t('questionnaires.selected', {
                count: wizard.questionnaires.length,
              })}
            </span>
            <button
              type="button"
              className="asg-wiz__link-button"
              onClick={wizard.clearQuestionnaires}
            >
              {t('questionnaires.clearSelection')}
            </button>
          </p>
        ) : null}
      </div>

      <div className="asg-wiz__finders">
        <label className="asg-wiz__search">
          <span className="visually-hidden">{t('questionnaires.search')}</span>
          <Icon name="search" size={16} />
          <input
            type="search"
            className="asg-wiz__input asg-wiz__input--search"
            value={choices.search}
            onChange={(event) => choices.setSearch(event.target.value)}
            placeholder={t('questionnaires.search')}
            autoComplete="off"
          />
        </label>
        <TagFilter
          loading={choices.loading}
          tags={choices.tagCounts}
          chosen={choices.tags}
          onToggle={choices.toggleTag}
          onClear={choices.clearTags}
        />
        <label className="asg-wiz__sort">
          <span className="visually-hidden">
            {t('questionnaires.sortLabel')}
          </span>
          <Select
            value={choices.sort}
            onChange={(event) =>
              choices.setSort(event.target.value as ChoiceSort)
            }
            options={CHOICE_SORTS.map((sort) => ({
              value: sort,
              label: t(`questionnaires.sort.${sort}`),
            }))}
          />
        </label>
      </div>

      {choices.tags.length > 0 ? (
        <div className="asg-wiz__active-filters">
          <TagList
            tags={choices.tags.map((key) => tagNames.get(key) ?? key)}
            label={t('questionnaires.chosenTags')}
            onRemove={(tag) =>
              choices.toggleTag(
                choices.tags.find((key) => tagNames.get(key) === tag) ?? tag,
              )
            }
            removeLabel={(tag) => t('questionnaires.removeTag', {tag})}
          />
        </div>
      ) : null}

      {choices.loading ? (
        <p className="asg-wiz__loading">
          <span className="asg-wiz__spin">
            <Icon name="loader" size={14} />
          </span>
          {t('loading')}
        </p>
      ) : choices.error ? (
        <ErrorState error={choices.error} onRetry={choices.retry} />
      ) : choices.total === 0 ? (
        <EmptyState title={t('questionnaires.none')} />
      ) : (
        <>
          <div className="asg-wiz__list-bar">
            <p className="asg-wiz__counter">
              <span aria-live="polite">
                {choices.filtered
                  ? t('questionnaires.counter', {
                      shown: choices.shown.length,
                      count: choices.total,
                    })
                  : t('questionnaires.total', {count: choices.total})}
              </span>
              {/* With nothing shown, the empty state below has the button. */}
              {choices.filtered && choices.shown.length > 0 ? (
                <button
                  type="button"
                  className="asg-wiz__link-button"
                  onClick={choices.clearFilters}
                >
                  {t('questionnaires.clearFilters')}
                </button>
              ) : null}
            </p>
            <div className="asg-wiz__list-actions">
              <Toggle
                checked={choices.onlyPicked}
                onChange={choices.setOnlyPicked}
                label={t('questionnaires.onlyPicked')}
                disabled={picked.size === 0}
              />
              {selectable.length > 0 ? (
                <button
                  type="button"
                  className="asg-wiz__link-button"
                  onClick={() =>
                    allVisiblePicked
                      ? wizard.unpickQuestionnaires(
                          selectable.map((row) => row.questionnaire_id),
                        )
                      : wizard.pickQuestionnaires(selectable.map(toPicked))
                  }
                >
                  {allVisiblePicked
                    ? t('questionnaires.unpickVisible', {
                        count: selectable.length,
                      })
                    : t('questionnaires.pickVisible', {
                        count: selectable.length,
                      })}
                </button>
              ) : null}
            </div>
          </div>

          {choices.shown.length === 0 ? (
            <EmptyState
              title={t('questionnaires.noMatches')}
              body={t('questionnaires.noMatchesHint')}
              action={
                <Button onClick={choices.clearFilters}>
                  {t('questionnaires.clearFilters')}
                </Button>
              }
            />
          ) : (
            <div
              className="asg-wiz__options"
              role="group"
              aria-label={t('questionnaires.listLabel')}
              onScroll={onScroll}
            >
              {choices.drawn.map((item) => {
                const empty = item.question_count === 0;
                const checked = picked.has(item.questionnaire_id);
                return (
                  <label
                    key={item.questionnaire_id}
                    className="asg-wiz__option asg-wiz__check"
                    data-checked={checked || undefined}
                    data-disabled={empty || undefined}
                  >
                    <input
                      type="checkbox"
                      checked={checked}
                      disabled={empty && !checked}
                      onChange={() =>
                        wizard.toggleQuestionnaire(toPicked(item))
                      }
                    />
                    <span className="asg-wiz__option-text">
                      <span className="asg-wiz__option-name">
                        <Highlight text={item.title} search={choices.term} />
                      </span>
                      <span className="asg-wiz__option-detail">
                        {[
                          t(`questionnaires.types.${item.type}`, {
                            defaultValue: item.type,
                          }),
                          empty
                            ? t('questionnaires.noQuestions')
                            : t('summary.questions', {
                                count: item.question_count,
                              }),
                          item.is_active ? null : t('questionnaires.inactive'),
                        ]
                          .filter(Boolean)
                          .join(' · ')}
                      </span>
                      <TagList
                        tags={item.tags}
                        label={t('questionnaires.tags')}
                        renderTag={(tag) => (
                          <Highlight text={tag} search={choices.term} />
                        )}
                      />
                    </span>
                  </label>
                );
              })}
              {choices.drawn.length < choices.shown.length ? (
                <button
                  type="button"
                  className="asg-wiz__more"
                  onClick={choices.drawMore}
                >
                  {t('questionnaires.showMore', {
                    count: choices.shown.length - choices.drawn.length,
                  })}
                </button>
              ) : null}
            </div>
          )}
        </>
      )}
    </section>
  );
}
