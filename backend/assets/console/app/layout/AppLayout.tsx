import {AssumeCustomer} from '@console/widgets/assume-customer';
import {UsageBanner} from '@console/widgets/usage-banner';
import {Suspense} from 'react';
import {Link, Outlet} from 'react-router';
import {type CrumbLink, LoadingState, PageCrumbsProvider} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Sidebar} from './Sidebar';
import './layout.css';

/**
 * Authenticated routes (PRD §10.1): sidebar + assumed-customer banner + usage banner + content. The two banners are
 * widgets owned by their items (assume-customer, usage-banner).
 */
const crumbLink: CrumbLink = ({to, className, children}) => (
  <Link to={to} className={className}>
    {children}
  </Link>
);

export function AppLayout() {
  const {t} = useTranslation('app');
  return (
    <PageCrumbsProvider
      rootLabel={t('workspace')}
      rootTo="/ai-experience"
      backLabel={t('back')}
      link={crumbLink}
    >
      <div className="shell">
        <Sidebar />
        <div className="shell__main">
          <AssumeCustomer placement="banner" />
          <UsageBanner />
          <main className="shell__content" id="content">
            <Suspense fallback={<LoadingState />}>
              <Outlet />
            </Suspense>
          </main>
        </div>
      </div>
    </PageCrumbsProvider>
  );
}
