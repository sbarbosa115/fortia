import {
  fetchQuestionnaires,
  type ListingParams,
  QUESTIONNAIRES_QUERY_KEY,
} from '@console/entities/questionnaire';
import {useDebouncedValue} from '@shared/lib';
import {useQuery} from '@tanstack/react-query';
import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  type ChoiceRow,
  type ChoiceSort,
  filterChoices,
  tagCounts,
} from './questionnaireFilter';

/** The largest page GET /questionnaire serves. */
const PAGE_SIZE = 100;
/** Rows rendered at a time: more appear near the end of the list, so a long account stays fast to draw. */
export const RENDER_STEP = 100;

const ALL: Omit<ListingParams, 'page'> = {
  search: '',
  type: null,
  isActive: null,
  sortBy: 'updated_at',
  order: 'desc',
  pageSize: PAGE_SIZE,
};

/** Every questionnaire of the account: the first page, then the others at once. */
async function fetchAllChoices(): Promise<ChoiceRow[]> {
  const first = await fetchQuestionnaires({...ALL, page: 1});
  const rest = await Promise.all(
    Array.from({length: Math.max(0, first.total_pages - 1)}, (_, index) =>
      fetchQuestionnaires({...ALL, page: index + 2}),
    ),
  );
  return [first, ...rest].flatMap((page) => page.items);
}

/**
 * The questionnaires step 1 lists. A picker, not a listing: the account's questionnaires load once (pages of 100) and
 * are searched (title and tags, 200 ms debounce), filtered by tags (ANY of the chosen ones), by "only picked" and
 * sorted here, so the counts are exact and "Select the visible ones" means what is on screen. Rows are drawn
 * {@link RENDER_STEP} at a time.
 */
export function useQuestionnaireChoices(picked: ReadonlySet<string>) {
  const {i18n} = useTranslation();
  const language = i18n.language;
  const [search, setSearch] = useState('');
  const [tags, setTags] = useState<string[]>([]);
  const [sort, setSort] = useState<ChoiceSort>('recent');
  const [onlyPicked, setOnlyPicked] = useState(false);
  const term = useDebouncedValue(search.trim(), 200);
  const query = useQuery({
    queryKey: [...QUESTIONNAIRES_QUERY_KEY, 'assignation-wizard', 'all'],
    queryFn: fetchAllChoices,
  });
  const rows = useMemo(() => query.data ?? [], [query.data]);
  // Nothing picked: "only picked" would show nothing, so it is off.
  const showOnlyPicked = onlyPicked && picked.size > 0;

  const counts = useMemo(() => tagCounts(rows, language), [rows, language]);
  const shown = useMemo(
    () =>
      filterChoices(
        rows,
        {search: term, tags, onlyPicked: showOnlyPicked, sort},
        picked,
        language,
      ),
    [rows, term, tags, showOnlyPicked, sort, picked, language],
  );

  const windowKey = [term, tags.join('|'), showOnlyPicked, sort].join('\n');
  const [drawn, setDrawn] = useState({key: windowKey, size: RENDER_STEP});
  const size = drawn.key === windowKey ? drawn.size : RENDER_STEP;

  const toggleTag = (key: string) =>
    setTags((current) =>
      current.includes(key)
        ? current.filter((each) => each !== key)
        : [...current, key],
    );

  return {
    search,
    setSearch,
    /** The search the list uses (and highlights): the typed text, 200 ms later. */
    term,
    /** Every tag of the account's questionnaires with how many have it, most used first. */
    tagCounts: counts,
    /** The chosen tags, folded. */
    tags,
    toggleTag,
    clearTags: () => setTags([]),
    sort,
    setSort,
    onlyPicked: showOnlyPicked,
    setOnlyPicked,
    filtered: term !== '' || tags.length > 0 || showOnlyPicked,
    clearFilters: () => {
      setSearch('');
      setTags([]);
      setOnlyPicked(false);
    },
    /** How many questionnaires the account has. */
    total: rows.length,
    /** Every row that passes the filters (what the counter and "Select the visible ones" use). */
    shown,
    /** The rows drawn now: the first ones of `shown`. */
    drawn: shown.slice(0, size),
    drawMore: () =>
      size < shown.length &&
      setDrawn({key: windowKey, size: size + RENDER_STEP}),
    loading: query.isPending,
    error: query.error,
    retry: () => void query.refetch(),
  };
}

export type QuestionnaireChoices = ReturnType<typeof useQuestionnaireChoices>;
