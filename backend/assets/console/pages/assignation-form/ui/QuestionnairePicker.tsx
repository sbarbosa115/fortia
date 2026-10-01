import {fetchQuestionnaires} from '@console/entities/questionnaire';
import {useDebouncedValue} from '@shared/lib';
import {Button, SearchInput, Spinner} from '@shared/ui';
import {useInfiniteQuery} from '@tanstack/react-query';
import {type UIEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';

const BATCH = 20;

/**
 * Questionnaire* (PRD §10.11): server-side search with a 300 ms debounce and infinite scroll in batches of 20 (the
 * list loads the next batch near its end; "Load more" too). Each search is its own query, so only the latest
 * response is shown.
 */
export function QuestionnairePicker({
  value,
  onChange,
  error,
  conflict,
}: {
  value: {id: string; title: string} | null;
  onChange: (questionnaire: {id: string; title: string}) => void;
  error: string | null;
  conflict: string | null;
}) {
  const {t} = useTranslation('pages.assignation-form');
  const [search, setSearch] = useState('');
  const term = useDebouncedValue(search.trim(), 300);
  const query = useInfiniteQuery({
    queryKey: ['questionnaires', 'picker', term],
    queryFn: ({pageParam}) =>
      fetchQuestionnaires({
        search: term,
        type: null,
        isActive: null,
        sortBy: 'updated_at',
        order: 'desc',
        page: pageParam,
        pageSize: BATCH,
      }),
    initialPageParam: 1,
    getNextPageParam: (last) =>
      last.page < last.total_pages ? last.page + 1 : undefined,
  });
  const items = query.data?.pages.flatMap((page) => page.items) ?? [];
  const loadMore = () => {
    if (query.hasNextPage && !query.isFetchingNextPage) {
      void query.fetchNextPage();
    }
  };
  const onScroll = (event: UIEvent<HTMLDivElement>) => {
    const box = event.currentTarget;
    if (box.scrollTop + box.clientHeight >= box.scrollHeight - 40) {
      loadMore();
    }
  };

  return (
    <fieldset className="asg-form__fieldset">
      <legend className="field__label">
        {t('questionnaire.label')}
        <span className="field__required" aria-hidden>
          *
        </span>
      </legend>
      {value ? (
        <p className="asg-form__selected">
          {t('questionnaire.selected', {title: value.title})}
        </p>
      ) : null}
      <SearchInput
        value={search}
        onChange={setSearch}
        label={t('questionnaire.search')}
        placeholder={t('questionnaire.placeholder')}
      />
      <div
        className="asg-form__options asg-form__options--scroll"
        role="radiogroup"
        aria-label={t('questionnaire.label')}
        onScroll={onScroll}
      >
        {query.isPending ? <Spinner /> : null}
        {!query.isPending && items.length === 0 ? (
          <p className="muted">{t('questionnaire.none')}</p>
        ) : null}
        {items.map((item) => (
          <label key={item.questionnaire_id} className="asg-form__option">
            <input
              type="radio"
              name="questionnaire"
              checked={item.questionnaire_id === value?.id}
              onChange={() =>
                onChange({id: item.questionnaire_id, title: item.title})
              }
            />
            <span>{item.title}</span>
            <span className="muted">
              {t('questionnaire.questions', {count: item.question_count})}
            </span>
          </label>
        ))}
        {query.hasNextPage ? (
          <Button
            size="sm"
            variant="ghost"
            loading={query.isFetchingNextPage}
            onClick={loadMore}
          >
            {t('questionnaire.loadMore')}
          </Button>
        ) : null}
      </div>
      {conflict ? (
        <p className="asg-form__notice" role="status">
          {t('questionnaire.conflict', {organization: conflict})}
        </p>
      ) : null}
      {error ? (
        <span className="field__error" role="alert">
          {error}
        </span>
      ) : null}
    </fieldset>
  );
}
