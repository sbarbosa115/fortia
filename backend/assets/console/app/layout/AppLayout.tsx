import {AssumeCustomer} from '@console/widgets/assume-customer';
import {UsageBanner} from '@console/widgets/usage-banner';
import {Suspense} from 'react';
import {Outlet} from 'react-router';
import {LoadingState} from '@shared/ui';
import {Sidebar} from './Sidebar';
import './layout.css';

/**
 * Authenticated routes (PRD §10.1): sidebar + assumed-customer banner + usage banner + content. The two banners are
 * widgets owned by their items (assume-customer, usage-banner).
 */
export function AppLayout() {
  return (
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
  );
}
