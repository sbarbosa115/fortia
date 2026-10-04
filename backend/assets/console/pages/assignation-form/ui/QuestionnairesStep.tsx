import {TagList} from '@console/entities/questionnaire';
import {ErrorState, Icon} from '@shared/ui';
import type {UIEvent} from 'react';
import {useTranslation} from 'react-i18next';
import type {AssignationWizardState} from '../model/useAssignationWizard';
import {useQuestionnaireChoices} from '../model/useQuestionnaireChoices';

/**
 * Step 1: the questionnaires the organization will answer — one checkbox each, searched by name and filtered by
 * one of the account's tags (the chips show only when some questionnaire has tags). A questionnaire without questions
 * can't be picked.
 */
export function QuestionnairesStep({wizard}: {wizard: AssignationWizardState}) {
  const {t} = useTranslation('pages.assignation-form');
  const choices = useQuestionnaireChoices();
  const picked = new Set(wizard.questionnaires.map((q) => q.id));
  const onScroll = (event: UIEvent<HTMLDivElement>) => {
    const box = event.currentTarget;
    if (box.scrollTop + box.clientHeight >= box.scrollHeight - 40) {
      choices.loadMore();
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

      {choices.tags.length > 0 ? (
        <div
          role="group"
          aria-label={t('questionnaires.tagLabel')}
          className="asg-wiz__filters"
        >
          <button
            type="button"
            className="asg-wiz__filter"
            aria-pressed={choices.tag === null}
            onClick={() => choices.setTag(null)}
          >
            {t('questionnaires.allTags')}
          </button>
          {choices.tags.map((tag) => (
            <button
              key={tag}
              type="button"
              className="asg-wiz__filter"
              aria-pressed={choices.tag === tag}
              onClick={() => choices.setTag(tag)}
            >
              {tag}
            </button>
          ))}
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
      ) : choices.items.length === 0 ? (
        <div className="asg-wiz__empty">
          {choices.filtered ? (
            <>
              <p>{t('questionnaires.noMatches')}</p>
              <button
                type="button"
                className="asg-wiz__link-button"
                onClick={choices.clearFilters}
              >
                {t('questionnaires.clearFilters')}
              </button>
            </>
          ) : (
            <p>{t('questionnaires.none')}</p>
          )}
        </div>
      ) : (
        <div
          className="asg-wiz__options"
          role="group"
          aria-label={t('questionnaires.listLabel')}
          onScroll={onScroll}
        >
          {choices.items.map((item) => {
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
                    wizard.toggleQuestionnaire({
                      id: item.questionnaire_id,
                      title: item.title,
                      questionCount: item.question_count,
                    })
                  }
                />
                <span className="asg-wiz__option-text">
                  <span className="asg-wiz__option-name">{item.title}</span>
                  <span className="asg-wiz__option-detail">
                    {[
                      t(`questionnaires.types.${item.type}`, {
                        defaultValue: item.type,
                      }),
                      empty
                        ? t('questionnaires.noQuestions')
                        : t('summary.questions', {count: item.question_count}),
                      item.is_active ? null : t('questionnaires.inactive'),
                    ]
                      .filter(Boolean)
                      .join(' · ')}
                  </span>
                  <TagList tags={item.tags} label={t('questionnaires.tags')} />
                </span>
              </label>
            );
          })}
          {choices.hasMore ? (
            <button
              type="button"
              className="asg-wiz__more"
              onClick={choices.loadMore}
              disabled={choices.loadingMore}
            >
              {t(
                choices.loadingMore
                  ? 'questionnaires.loadingMore'
                  : 'questionnaires.loadMore',
              )}
            </button>
          ) : null}
        </div>
      )}
    </section>
  );
}
