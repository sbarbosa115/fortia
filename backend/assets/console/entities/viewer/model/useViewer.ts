import {useMemo, useSyncExternalStore} from 'react';
import {getAssumed, subscribeAssumed} from './assume';
import {claimsOf, getSession, subscribeSession} from './session';
import {type Viewer, viewerFrom} from './viewer';

/** The signed-in console user, re-rendering on sign-in, sign-out, refresh (in any tab) and assuming. */
export function useViewer(): Viewer {
  const session = useSyncExternalStore(subscribeSession, getSession);
  const assumed = useSyncExternalStore(subscribeAssumed, getAssumed);
  return useMemo(
    () => viewerFrom(claimsOf(session), assumed),
    [session, assumed],
  );
}
