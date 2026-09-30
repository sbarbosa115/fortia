import type {SortBy, SortOrder} from '@console/entities/questionnaire';
import type {TimeZoneMode} from '@shared/lib';
import {FilterBar, SearchInput, Select} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {
  QuestionnaireListing,
  StatusFilter,
  TypeFilter,
} from '../model/useQuestionnaireListing';

/** Search (⌘/Ctrl+K), type, state, sort and order, and the Local/UTC preference (PRD §10.6). */
export function ListingToolbar({listing}: {listing: QuestionnaireListing}) {
  const {t} = useTranslation('pages.questionnaires');
  return (
    <FilterBar>
      <SearchInput
        shortcut
        value={listing.search}
        onChange={listing.setSearch}
        label={t('toolbar.search')}
        placeholder={t('toolbar.searchPlaceholder')}
      />
      <Select
        aria-label={t('toolbar.type')}
        value={listing.type}
        onChange={(e) => listing.setType(e.target.value as TypeFilter)}
        options={[
          {value: 'all', label: t('filters.allTypes')},
          {value: 'default', label: t('kind.default')},
          {value: 'quiz_funnel', label: t('kind.quiz_funnel')},
          {value: 'diagnostic', label: t('kind.diagnostic')},
          {value: 'process_mapping', label: t('kind.process_mapping')},
        ]}
      />
      <Select
        aria-label={t('toolbar.status')}
        value={listing.status}
        onChange={(e) => listing.setStatus(e.target.value as StatusFilter)}
        options={[
          {value: 'all', label: t('filters.allStatus')},
          {value: 'active', label: t('filters.active')},
          {value: 'inactive', label: t('filters.inactive')},
        ]}
      />
      <Select
        aria-label={t('toolbar.sortBy')}
        value={listing.sortBy}
        onChange={(e) => listing.setSortBy(e.target.value as SortBy)}
        options={[
          {value: 'created_at', label: t('filters.sortCreated')},
          {value: 'updated_at', label: t('filters.sortUpdated')},
        ]}
      />
      <Select
        aria-label={t('toolbar.order')}
        value={listing.order}
        onChange={(e) => listing.setOrder(e.target.value as SortOrder)}
        options={[
          {value: 'desc', label: t('filters.desc')},
          {value: 'asc', label: t('filters.asc')},
        ]}
      />
      <Select
        aria-label={t('toolbar.timeZone')}
        value={listing.timeZone}
        onChange={(e) => listing.setTimeZone(e.target.value as TimeZoneMode)}
        options={[
          {value: 'local', label: t('filters.local')},
          {value: 'utc', label: t('filters.utc')},
        ]}
      />
    </FilterBar>
  );
}
