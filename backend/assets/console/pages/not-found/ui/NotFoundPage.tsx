import {useDocumentTitle} from '@shared/lib';
import {EmptyState} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';

/** PRD §10.1: "404 / Oops! Page not found / Return to Home". */
export function NotFoundPage() {
  const {t} = useTranslation('pages.not-found');
  useDocumentTitle(`Mappi - ${t('code')}`);
  return (
    <EmptyState
      title={
        <>
          <span
            className="serif-heading"
            style={{display: 'block', fontSize: 56}}
          >
            {t('code')}
          </span>
          {t('title')}
        </>
      }
      action={
        <Link to="/ai-experience" className="btn btn--primary">
          {t('home')}
        </Link>
      }
    />
  );
}
