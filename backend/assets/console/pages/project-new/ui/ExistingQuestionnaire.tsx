import {fetchQuestionnaires} from '@console/entities/questionnaire';
import {joinClasses, useDebouncedValue} from '@shared/lib';
import {Icon} from '@shared/ui';
import {useInfiniteQuery} from '@tanstack/react-query';
import {
  type KeyboardEvent,
  type UIEvent,
  useEffect,
  useId,
  useRef,
  useState,
} from 'react';
import {useTranslation} from 'react-i18next';
import type {ProjectWizardState} from '../model/useProjectWizard';

const BATCH = 20;

/**
 * Step 1 with a questionnaire the account already has, of any type: a dropdown you can type into (server-side search
 * with a 300 ms debounce, 20 at a time, more as the list's end scrolls into view). Enter or Space opens it, the
 * arrows move, Enter picks, Escape closes. It is followed up as it is (or a copy, when another organization already
 * follows it).
 */
export function ExistingQuestionnaire({wizard}: {wizard: ProjectWizardState}) {
  const {t} = useTranslation('pages.project-new');
  const id = useId();
  const triggerId = `${id}-trigger`;
  const listId = `${id}-list`;
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [active, setActive] = useState(0);
  const root = useRef<HTMLDivElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
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
    enabled: open,
  });
  const items = query.data?.pages.flatMap((page) => page.items) ?? [];
  const chosen = wizard.existing;

  // A click outside closes it.
  useEffect(() => {
    if (!open) return;
    const onDown = (event: MouseEvent) => {
      if (!root.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', onDown);
    return () => document.removeEventListener('mousedown', onDown);
  }, [open]);

  const close = () => {
    setOpen(false);
    trigger.current?.focus();
  };
  const pick = (index: number) => {
    const item = items[index];
    if (!item) return;
    wizard.chooseExisting({
      id: item.questionnaire_id,
      title: item.title,
      questionCount: item.question_count,
    });
    close();
  };
  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setActive((i) => Math.min(i + 1, items.length - 1));
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      setActive((i) => Math.max(i - 1, 0));
    } else if (event.key === 'Enter') {
      event.preventDefault();
      pick(active);
    } else if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      close();
    }
  };
  const onScroll = (event: UIEvent<HTMLDivElement>) => {
    const list = event.currentTarget;
    if (
      list.scrollTop + list.clientHeight >= list.scrollHeight - 24 &&
      query.hasNextPage &&
      !query.isFetchingNextPage
    ) {
      void query.fetchNextPage();
    }
  };

  return (
    <div className="prj-new__existing">
      <div className="prj-dlg__field">
        <label htmlFor={triggerId} className="prj-new__label">
          {t('questions.existingLabel')}
        </label>
        <div className="prj-combo" ref={root}>
          <button
            ref={trigger}
            id={triggerId}
            type="button"
            role="combobox"
            aria-expanded={open}
            aria-controls={open ? listId : undefined}
            aria-haspopup="listbox"
            className="prj-combo__trigger"
            onClick={() => {
              setActive(0);
              setOpen((value) => !value);
            }}
          >
            <span
              className={joinClasses(
                'prj-combo__value',
                !chosen && 'prj-combo__value--placeholder',
              )}
            >
              {chosen?.title || t('questions.existingPlaceholder')}
            </span>
            <Icon name="chevrons-up-down" size={16} />
          </button>
          {open ? (
            <div className="prj-combo__panel">
              <div className="prj-combo__search">
                <Icon name="search" size={16} />
                <input
                  autoFocus
                  className="prj-combo__input"
                  value={search}
                  placeholder={t('questions.existingSearch')}
                  aria-label={t('questions.existingSearch')}
                  aria-controls={listId}
                  aria-activedescendant={
                    items[active] ? `${id}-option-${active}` : undefined
                  }
                  onChange={(event) => {
                    setSearch(event.target.value);
                    setActive(0);
                  }}
                  onKeyDown={onKeyDown}
                />
              </div>
              <div
                id={listId}
                role="listbox"
                aria-label={t('questions.existingLabel')}
                className="prj-combo__list"
                onScroll={onScroll}
              >
                {query.isPending ? (
                  <p className="prj-combo__state">
                    {t('questions.existingLoading')}
                  </p>
                ) : query.isError ? (
                  <p className="prj-combo__state prj-combo__state--error">
                    {t('errors.loadQuestionnaires')}
                  </p>
                ) : items.length === 0 ? (
                  <p className="prj-combo__state">
                    {t('questions.existingNone')}
                  </p>
                ) : null}
                {items.map((item, index) => (
                  <div
                    key={item.questionnaire_id}
                    id={`${id}-option-${index}`}
                    role="option"
                    aria-selected={item.questionnaire_id === chosen?.id}
                    className={joinClasses(
                      'prj-combo__option',
                      index === active && 'prj-combo__option--active',
                    )}
                    onMouseEnter={() => setActive(index)}
                    onClick={() => pick(index)}
                  >
                    <span
                      className={joinClasses(
                        'prj-combo__check',
                        item.questionnaire_id === chosen?.id &&
                          'prj-combo__check--on',
                      )}
                    >
                      <Icon name="check" size={16} />
                    </span>
                    <span className="prj-combo__option-label">
                      {item.title}
                    </span>
                  </div>
                ))}
                {query.hasNextPage ? (
                  <p className="prj-combo__more">
                    <span className="prj-new__spin">
                      <Icon name="loader" size={14} />
                    </span>
                    {t('questions.loadingMore')}
                  </p>
                ) : null}
              </div>
            </div>
          ) : null}
        </div>
      </div>
      {chosen ? (
        <div className="prj-new__chosen">
          <span className="prj-new__chosen-count">
            {t('summary.questions', {count: chosen.questionCount})}
          </span>
          {/* A new tab: leaving the page would lose the wizard, which saves nothing until Create. */}
          <a
            href={`/console/questionnaires/${chosen.id}/edit`}
            target="_blank"
            rel="noreferrer"
            className="prj-new__chosen-link"
          >
            {t('questions.existingEdit')}
            <Icon name="external" size={14} />
          </a>
        </div>
      ) : null}
    </div>
  );
}
