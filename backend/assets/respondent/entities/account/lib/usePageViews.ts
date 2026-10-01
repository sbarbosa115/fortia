import {useEffect} from 'react';
import {useLocation} from 'react-router';
import {trackPageView} from './tracking';

/** A page view on every route or query change, never on a hash change (PRD §9.16). */
export function usePageViews(enabled: boolean): void {
  const {pathname, search} = useLocation();
  useEffect(() => {
    if (enabled) {
      trackPageView(pathname + search);
    }
  }, [enabled, pathname, search]);
}
