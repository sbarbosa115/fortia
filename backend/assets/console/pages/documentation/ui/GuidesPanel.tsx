import {
  GUIDE_TOPICS,
  type Guide,
  type GuideTopic,
  guideLanguage,
  guidesFor,
  isGuideTopic,
  readingMinutes,
  searchGuides,
} from '@console/entities/guide';
import {
  Badge,
  Button,
  Card,
  EmptyState,
  FilterBar,
  Icon,
  SearchInput,
  Select,
} from '@shared/ui';
import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';

/** The guide cards with a search (every word, case- and accent-insensitive) and a topic filter (PRD §10.18). */
export function GuidesPanel() {
  const {t, i18n} = useTranslation('pages.documentation');
  const {t: tShared} = useTranslation('shared');
  const {t: tGuide} = useTranslation('entities.guide');
  const language = guideLanguage(i18n.language);
  const guides = useMemo(() => guidesFor(language), [language]);
  const [query, setQuery] = useState('');
  const [topic, setTopic] = useState<GuideTopic | null>(null);
  const shown = searchGuides(guides, {query, topic});

  const clear = () => {
    setQuery('');
    setTopic(null);
  };

  return (
    <>
      <FilterBar>
        <SearchInput
          value={query}
          onChange={setQuery}
          label={t('guides.search')}
          placeholder={t('guides.searchPlaceholder')}
          shortcut
        />
        <Select
          aria-label={t('guides.topic')}
          value={topic ?? ''}
          onChange={(event) =>
            setTopic(
              isGuideTopic(event.target.value) ? event.target.value : null,
            )
          }
          options={[
            {value: '', label: t('guides.allTopics')},
            ...GUIDE_TOPICS.map((value) => ({
              value,
              label: tGuide(`topics.${value}`),
            })),
          ]}
        />
        <span className="docs-count" aria-live="polite">
          {t('guides.count', {count: shown.length})}
        </span>
      </FilterBar>
      {shown.length === 0 ? (
        <Card>
          <EmptyState
            title={t('guides.noMatches')}
            body={t('guides.noMatchesBody')}
            action={
              <Button onClick={clear}>{tShared('actions.clearFilters')}</Button>
            }
          />
        </Card>
      ) : (
        <ul className="docs-grid">
          {shown.map((guide) => (
            <li key={guide.id}>
              <GuideCard guide={guide} />
            </li>
          ))}
        </ul>
      )}
    </>
  );
}

function GuideCard({guide}: {guide: Guide}) {
  const {t} = useTranslation('pages.documentation');
  const {t: tGuide} = useTranslation('entities.guide');
  const titleId = `guide-${guide.id}`;
  return (
    <Card className="docs-card">
      <article aria-labelledby={titleId}>
        <div className="docs-card__meta">
          <Badge tone="accent">{tGuide(`topics.${guide.topic}`)}</Badge>
          <span className="docs-card__time">
            {tGuide('readingTime', {count: readingMinutes(guide)})}
          </span>
        </div>
        <h2 className="docs-card__title" id={titleId}>
          <Link to={`/documentation/guides/${guide.id}`}>{guide.title}</Link>
        </h2>
        <p className="docs-card__summary">{guide.summary}</p>
        <Link
          className="docs-card__read"
          to={`/documentation/guides/${guide.id}`}
          aria-label={`${t('guides.read')}: ${guide.title}`}
        >
          {t('guides.read')}
          <Icon name="chevron-right" size={16} />
        </Link>
      </article>
    </Card>
  );
}
