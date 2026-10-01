import {fetchQuestionnaires} from '@console/entities/questionnaire';
import {ChatPanel} from '@console/widgets/chat-panel';
import {useDebouncedValue} from '@shared/lib';
import {
  Badge,
  Button,
  Card,
  CardBody,
  SearchInput,
  Spinner,
  Tabs,
} from '@shared/ui';
import {useInfiniteQuery} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {ProjectWizardState} from '../model/useProjectWizard';
import type {QuestionSource} from '../model/wizard';

const BATCH = 20;

/**
 * Step 1 (PRD §10.12): the questions come from the chat in draft mode — it drafts, the user asks for changes, and
 * approving the draft saves the questionnaire — or from a questionnaire the account already has.
 */
export function QuestionsStep({wizard}: {wizard: ProjectWizardState}) {
  const {t} = useTranslation('pages.project-new');
  const copy = wizard.approved
    ? 'chatDone'
    : wizard.draft
      ? 'chatChanges'
      : 'chat';

  return (
    <div className="prj-new__stack">
      <Tabs<QuestionSource>
        label={t('questions.sourceLabel')}
        tabs={[
          {key: 'chat', label: t('questions.sourceChat')},
          {key: 'existing', label: t('questions.sourceExisting')},
        ]}
        active={wizard.source}
        onChange={wizard.chooseSource}
      />
      {wizard.source === 'chat' ? (
        <Card>
          <CardBody>
            <div className="prj-new__stack">
              <div>
                <h3 className="prj-new__card-title">
                  {t(`questions.${copy}Title`)}
                </h3>
                <p className="muted">{t(`questions.${copy}Hint`)}</p>
              </div>
              <ChatPanel
                chat={wizard.chat}
                greeting={t('questions.greeting')}
                disabledReason={wizard.chatReason}
              />
              {wizard.draft ? (
                <div className="prj-new__draft" role="status">
                  <span>
                    <strong>
                      {wizard.draft.title || t('questions.untitled')}
                    </strong>
                    {' · '}
                    {t('summary.questions', {
                      count: wizard.draft.questionCount,
                    })}
                  </span>
                  <Badge tone={wizard.approved ? 'success' : 'warning'}>
                    {wizard.approved
                      ? t('questions.approvedBadge')
                      : t('questions.draftBadge')}
                  </Badge>
                </div>
              ) : null}
            </div>
          </CardBody>
        </Card>
      ) : (
        <Card>
          <CardBody>
            <ExistingQuestionnaire wizard={wizard} />
          </CardBody>
        </Card>
      )}
    </div>
  );
}

/**
 * A questionnaire the account has, of any type: server-side search with a 300 ms debounce, 20 at a time. It is
 * followed up as it is (or a copy, when another organization already follows it).
 */
function ExistingQuestionnaire({wizard}: {wizard: ProjectWizardState}) {
  const {t} = useTranslation('pages.project-new');
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
  const chosen = wizard.existing;

  return (
    <fieldset className="prj-new__fieldset">
      <legend className="prj-new__card-title">
        {t('questions.existingTitle')}
      </legend>
      <p className="muted">{t('questions.existingHint')}</p>
      {chosen ? (
        <p className="prj-new__chosen">
          {t('questions.existingChosen', {title: chosen.title})}{' '}
          <a
            href={`/console/questionnaires/${chosen.id}/edit`}
            target="_blank"
            rel="noreferrer"
          >
            {t('questions.existingEdit')}
          </a>
        </p>
      ) : null}
      <SearchInput
        value={search}
        onChange={setSearch}
        label={t('questions.existingSearch')}
        placeholder={t('questions.existingSearch')}
      />
      <div
        className="prj-new__options prj-new__options--scroll"
        role="radiogroup"
        aria-label={t('questions.existingTitle')}
      >
        {query.isPending ? <Spinner /> : null}
        {query.isError ? (
          <p className="field__error">{t('errors.loadQuestionnaires')}</p>
        ) : null}
        {!query.isPending && !query.isError && items.length === 0 ? (
          <p className="muted">{t('questions.existingNone')}</p>
        ) : null}
        {items.map((item) => (
          <label key={item.questionnaire_id} className="prj-new__option">
            <input
              type="radio"
              name="existing-questionnaire"
              checked={item.questionnaire_id === chosen?.id}
              onChange={() =>
                wizard.chooseExisting({
                  id: item.questionnaire_id,
                  title: item.title,
                  questionCount: item.question_count,
                })
              }
            />
            <span>{item.title}</span>
            <span className="muted">
              {t('summary.questions', {count: item.question_count})}
            </span>
          </label>
        ))}
        {query.hasNextPage ? (
          <Button
            size="sm"
            variant="ghost"
            loading={query.isFetchingNextPage}
            onClick={() => void query.fetchNextPage()}
          >
            {t('questions.loadMore')}
          </Button>
        ) : null}
      </div>
    </fieldset>
  );
}
