import {useTranslation} from 'react-i18next';
import {Button, IconButton} from './Button';
import {Icon} from './Icon';
import {Select} from './Field';

/** Numbered pages with a window of 5 and "{start}–{end} of {total}" (PRD §10.6). */
export function Pagination({
  page,
  pageSize,
  total,
  onPage,
  pageSizes,
  onPageSize,
}: {
  page: number;
  pageSize: number;
  total: number;
  onPage: (page: number) => void;
  pageSizes?: number[];
  onPageSize?: (size: number) => void;
}) {
  const {t} = useTranslation('shared');
  const pages = Math.max(1, Math.ceil(total / pageSize));
  const first = Math.max(1, Math.min(page - 2, pages - 4));
  const visible = Array.from({length: Math.min(5, pages)}, (_, i) => first + i);
  const start = total === 0 ? 0 : (page - 1) * pageSize + 1;
  const end = Math.min(total, page * pageSize);
  return (
    <nav className="pagination" aria-label={t('pagination.page', {page})}>
      <span>{t('pagination.range', {start, end, total})}</span>
      <div className="row">
        {pageSizes && onPageSize ? (
          <Select
            aria-label={t('pagination.pageSize')}
            value={String(pageSize)}
            onChange={(e) => onPageSize(Number(e.target.value))}
            options={pageSizes.map((size) => ({
              value: String(size),
              label: String(size),
            }))}
            style={{width: 'auto', minHeight: 32}}
          />
        ) : null}
        <IconButton
          size="sm"
          label={t('actions.previous')}
          icon={<Icon name="chevron-left" />}
          disabled={page <= 1}
          onClick={() => onPage(page - 1)}
        />
        <div className="pagination__pages">
          {visible.map((n) => (
            <Button
              key={n}
              size="sm"
              variant="ghost"
              className="pagination__page"
              aria-current={n === page ? 'page' : undefined}
              aria-label={t('pagination.goToPage', {page: n})}
              onClick={() => onPage(n)}
            >
              {n}
            </Button>
          ))}
        </div>
        <IconButton
          size="sm"
          label={t('actions.next')}
          icon={<Icon name="chevron-right" />}
          disabled={page >= pages}
          onClick={() => onPage(page + 1)}
        />
      </div>
    </nav>
  );
}

/** Previous / Next over an opaque cursor, with "Page N" (PRD §10.8). */
export function CursorPagination({
  page,
  hasPrevious,
  hasNext,
  onPrevious,
  onNext,
}: {
  page: number;
  hasPrevious: boolean;
  hasNext: boolean;
  onPrevious: () => void;
  onNext: () => void;
}) {
  const {t} = useTranslation('shared');
  return (
    <nav className="pagination" aria-label={t('pagination.page', {page})}>
      <span>{t('pagination.page', {page})}</span>
      <div className="row">
        <Button size="sm" disabled={!hasPrevious} onClick={onPrevious}>
          {t('actions.previous')}
        </Button>
        <Button size="sm" disabled={!hasNext} onClick={onNext}>
          {t('actions.next')}
        </Button>
      </div>
    </nav>
  );
}
