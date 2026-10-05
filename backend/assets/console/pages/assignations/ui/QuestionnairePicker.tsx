import {
  fetchQuestionnaires,
  QUESTIONNAIRES_QUERY_KEY,
} from '@console/entities/questionnaire';
import {useDebouncedValue} from '@shared/lib';
import {Button, ErrorState, Icon} from '@shared/ui';
import {keepPreviousData, useQuery} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';

const PAGE_SIZE = 20;

/**
 * Picks questionnaires to add to an assignation: the account's questionnaires, searched by name or tag (300 ms
 * debounce), most recently changed first, without the ones it already has. A click adds one; a questionnaire without
 * questions can't be added.
 */
export function QuestionnairePicker({
  excluded,
  onPick,
  onDone,
}: {
  /** The questionnaires the assignation already has (or that were just added). */
  excluded: ReadonlySet<string>;
  onPick: (questionnaire: {questionnaireId: string; title: string}) => void;
  onDone: () => void;
}) {
  const {t} = useTranslation('pages.assignations');
  const [search, setSearch] = useState('');
  const term = useDebouncedValue(search.trim(), 300);
  // Ask for more than a page so the ones already there don't leave the list short.
  const pageSize = PAGE_SIZE + excluded.size;
  const query = useQuery({
    queryKey: [...QUESTIONNAIRES_QUERY_KEY, 'edit-assignation', term, pageSize],
    queryFn: () =>
      fetchQuestionnaires({
        search: term,
        type: null,
        isActive: null,
        sortBy: 'updated_at',
        order: 'desc',
        page: 1,
        pageSize: Math.min(pageSize, 100),
      }),
    placeholderData: keepPreviousData,
  });
  const rows = (query.data?.items ?? [])
    .filter((row) => !excluded.has(row.questionnaire_id))
    .slice(0, PAGE_SIZE);

  return (
    <div className="project-form__picker">
      <label className="project-form__picker-search">
        <span className="visually-hidden">{t('form.pickerSearch')}</span>
        <Icon name="search" size={16} />
        <input
          type="search"
          value={search}
          autoComplete="off"
          placeholder={t('form.pickerSearch')}
          onChange={(event) => setSearch(event.target.value)}
        />
      </label>
      {query.isPending ? (
        <p className="project-form__note">{t('form.pickerLoading')}</p>
      ) : query.error ? (
        <ErrorState error={query.error} onRetry={() => void query.refetch()} />
      ) : rows.length === 0 ? (
        <p className="project-form__note">
          {term === ''
            ? t('form.pickerNone')
            : t('form.pickerNoMatches', {query: term})}
        </p>
      ) : (
        <ul
          className="project-form__picker-list"
          aria-label={t('form.pickerLabel')}
        >
          {rows.map((row) => {
            const empty = row.question_count === 0;
            return (
              <li key={row.questionnaire_id}>
                <button
                  type="button"
                  className="project-form__picker-item"
                  disabled={empty}
                  aria-label={t('form.addOne', {name: row.title})}
                  onClick={() =>
                    onPick({
                      questionnaireId: row.questionnaire_id,
                      title: row.title,
                    })
                  }
                >
                  <Icon name="plus" size={14} />
                  <span className="project-form__picker-text">
                    <span className="project-form__picker-name">
                      {row.title}
                    </span>
                    <span className="project-form__picker-detail">
                      {empty
                        ? t('form.noQuestions')
                        : t('form.questions', {count: row.question_count})}
                    </span>
                  </span>
                </button>
              </li>
            );
          })}
        </ul>
      )}
      <div className="project-form__picker-foot">
        <Button size="sm" onClick={onDone}>
          {t('form.pickerDone')}
        </Button>
      </div>
    </div>
  );
}
