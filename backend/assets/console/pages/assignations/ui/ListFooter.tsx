import {Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';

/** A window of at most 5 page numbers around the current page. */
function pageWindow(current: number, total: number): number[] {
  const span = 5;
  let start = Math.max(1, current - Math.floor(span / 2));
  const end = Math.min(total, start + span - 1);
  start = Math.max(1, end - span + 1);
  return Array.from({length: end - start + 1}, (_, index) => start + index);
}

/** "1–10 of 23" and, past one page, Previous · numbered pages · Next. */
export function ListFooter({
  page,
  pageSize,
  total,
  onPage,
}: {
  page: number;
  pageSize: number;
  total: number;
  onPage: (page: number) => void;
}) {
  const {t} = useTranslation('shared');
  const pages = Math.max(1, Math.ceil(total / pageSize));
  const start = (page - 1) * pageSize + 1;
  const end = Math.min(page * pageSize, total);
  return (
    <div className="projects-footer">
      <p className="projects-footer__range">
        {t('pagination.range', {start, end, total})}
      </p>
      {pages > 1 ? (
        <nav
          className="projects-pager"
          aria-label={t('pagination.page', {page})}
        >
          <ul>
            <li>
              <button
                type="button"
                className="projects-pager__step"
                disabled={page === 1}
                onClick={() => onPage(Math.max(1, page - 1))}
              >
                <Icon name="chevron-left" size={16} />
                <span>{t('actions.previous')}</span>
              </button>
            </li>
            {pageWindow(page, pages).map((number) => (
              <li key={number}>
                <button
                  type="button"
                  className="projects-pager__page"
                  aria-current={number === page ? 'page' : undefined}
                  aria-label={t('pagination.goToPage', {page: number})}
                  onClick={() => onPage(number)}
                >
                  {number}
                </button>
              </li>
            ))}
            <li>
              <button
                type="button"
                className="projects-pager__step"
                data-side="next"
                disabled={page === pages}
                onClick={() => onPage(Math.min(pages, page + 1))}
              >
                <span>{t('actions.next')}</span>
                <Icon name="chevron-right" size={16} />
              </button>
            </li>
          </ul>
        </nav>
      ) : null}
    </div>
  );
}
