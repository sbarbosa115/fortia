import {PROJECT_TABS, type Project} from '@console/entities/project';
import {ErrorState, Icon} from '@shared/ui';
import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {PAGE_SIZE, type ProjectListing} from '../model/useProjectListing';
import {ListFooter} from './ListFooter';
import {ProjectTable} from './ProjectTable';
import {StatusLegend} from './StatusLegend';

/**
 * Loading (a spinner), error (with a retry), "no projects yet" (what the section is for and its first action), or
 * the list card: status pills and search over the table ("filtered to nothing" inside it), the range and pages, and
 * the status legend under it.
 */
export function ProjectsBody({
  listing,
  emptyAction,
  changeReason,
  onEdit,
  onDelete,
}: {
  listing: ProjectListing;
  emptyAction: ReactNode;
  changeReason: string | null;
  onEdit: (project: Project) => void;
  onDelete: (project: Project) => void;
}) {
  const {t} = useTranslation('pages.projects');
  const {query} = listing;

  if (query.isPending) {
    return (
      <div className="projects-loading" role="status" aria-label={t('loading')}>
        <Icon name="loader" size={32} />
      </div>
    );
  }
  if (query.isError) {
    return (
      <div className="projects-card">
        <ErrorState error={query.error} onRetry={() => void query.refetch()} />
      </div>
    );
  }
  if (listing.rows.length === 0 && listing.page === 1 && !listing.hasFilters) {
    return (
      <div className="projects-card projects-empty">
        <p>{t('empty')}</p>
        {emptyAction}
      </div>
    );
  }

  const searched = listing.appliedSearch;
  return (
    <>
      <section aria-label={t('listLabel')} className="projects-card">
        <div className="projects-toolbar">
          <div
            role="group"
            aria-label={t('filtersLabel')}
            className="projects-filters"
          >
            {PROJECT_TABS.map((tab) => (
              <button
                key={tab.key}
                type="button"
                className="projects-filters__pill"
                aria-pressed={listing.tab === tab.key}
                onClick={() => listing.setTab(tab.key)}
              >
                {t(`tabs.${tab.key}`)}
              </button>
            ))}
          </div>
          <label className="projects-search">
            <span className="visually-hidden">{t('searchLabel')}</span>
            <span className="projects-search__icon" aria-hidden>
              <Icon name="search" size={16} />
            </span>
            <input
              type="search"
              value={listing.search}
              onChange={(event) => listing.setSearch(event.target.value)}
              placeholder={t('searchPlaceholder')}
            />
          </label>
        </div>

        {listing.rows.length === 0 ? (
          <div className="projects-filtered">
            <p>
              {searched
                ? t('noResultsSearch', {query: searched})
                : t('noResults')}
            </p>
            <button
              type="button"
              className="pill-action"
              data-tone="quiet"
              onClick={listing.clearFilters}
            >
              {t('clearFilters')}
            </button>
          </div>
        ) : (
          <ProjectTable
            rows={listing.rows}
            changeReason={changeReason}
            onEdit={onEdit}
            onDelete={onDelete}
          />
        )}

        {listing.total > 0 ? (
          <ListFooter
            page={listing.page}
            pageSize={PAGE_SIZE}
            total={listing.total}
            onPage={listing.setPage}
          />
        ) : null}
      </section>
      <StatusLegend />
    </>
  );
}
