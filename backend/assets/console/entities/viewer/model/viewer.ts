import type {Claims} from './session';
import type {AssumedCustomer} from './assume';

export type Role = 'Admin' | 'Customer-Admin' | 'Customer-Read-Only';

/**
 * Who is using the console and what they may do (PRD §4.2): write permission = root, Admin or Customer-Admin,
 * read from the full list of groups; the displayed role follows Admin > Customer-Admin > Customer-Read-Only. While a
 * super-admin assumes a customer, they act as its root Customer-Admin (§4.4).
 */
export type Viewer = {
  signedIn: boolean;
  userId: string;
  email: string;
  name: string;
  customerId: string;
  role: Role;
  isRoot: boolean;
  /** A super-admin not assuming anyone: sees every account, passes every plan gate. */
  isAdmin: boolean;
  /** A super-admin, assuming or not (shows the customer selector). */
  isPlatformAdmin: boolean;
  canWrite: boolean;
  assumed: AssumedCustomer | null;
};

export const SIGNED_OUT: Viewer = {
  signedIn: false,
  userId: '',
  email: '',
  name: '',
  customerId: '',
  role: 'Customer-Read-Only',
  isRoot: false,
  isAdmin: false,
  isPlatformAdmin: false,
  canWrite: false,
  assumed: null,
};

export function viewerFrom(
  claims: Claims | null,
  assumed: AssumedCustomer | null,
): Viewer {
  if (!claims) {
    return SIGNED_OUT;
  }
  const groups = claims.groups ?? [];
  const isPlatformAdmin = groups.includes('Admin');
  if (isPlatformAdmin && assumed) {
    return {
      signedIn: true,
      userId: claims.sub,
      email: assumed.email,
      name: assumed.name,
      customerId: assumed.id,
      role: 'Customer-Admin',
      isRoot: true,
      isAdmin: false,
      isPlatformAdmin: true,
      canWrite: true,
      assumed,
    };
  }
  const isRoot = claims.root === 'true';
  const role: Role = isPlatformAdmin
    ? 'Admin'
    : groups.includes('Customer-Admin')
      ? 'Customer-Admin'
      : 'Customer-Read-Only';
  return {
    signedIn: true,
    userId: claims.sub,
    email: claims.email,
    name: claims.name,
    customerId: claims.customer_id,
    role,
    isRoot,
    isAdmin: isPlatformAdmin,
    isPlatformAdmin,
    canWrite: isRoot || isPlatformAdmin || groups.includes('Customer-Admin'),
    assumed: null,
  };
}
