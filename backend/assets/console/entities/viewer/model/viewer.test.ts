import {describe, expect, it} from 'vitest';
import {needsRefresh} from './session';
import type {Claims} from './session';
import {viewerFrom} from './viewer';

const claims = (overrides: Partial<Claims>): Claims => ({
  sub: 'u1',
  email: 'reader@acme.test',
  name: 'Rita',
  customer_id: 'ACME0001',
  root: 'false',
  groups: ['Customer-Read-Only'],
  exp: 0,
  ...overrides,
});

describe('viewerFrom', () => {
  it('lets a read-only member read but not write', () => {
    const viewer = viewerFrom(claims({}), null);
    expect(viewer.canWrite).toBe(false);
    expect(viewer.role).toBe('Customer-Read-Only');
  });

  it('gives write permission to the root even with a read-only group', () => {
    expect(viewerFrom(claims({root: 'true'}), null).canWrite).toBe(true);
  });

  it('shows Admin first when a user has several groups', () => {
    const viewer = viewerFrom(
      claims({groups: ['Customer-Read-Only', 'Admin']}),
      null,
    );
    expect(viewer.role).toBe('Admin');
    expect(viewer.isAdmin).toBe(true);
  });

  it('turns an assuming super-admin into the root Customer-Admin of that account', () => {
    const viewer = viewerFrom(claims({groups: ['Admin']}), {
      id: 'GLOBEX01',
      name: 'Gael',
      email: 'owner@globex.test',
    });
    expect(viewer).toMatchObject({
      customerId: 'GLOBEX01',
      role: 'Customer-Admin',
      isRoot: true,
      isAdmin: false,
      isPlatformAdmin: true,
      canWrite: true,
    });
  });

  it('ignores an assumed account for someone who is not a super-admin', () => {
    const viewer = viewerFrom(claims({}), {
      id: 'X',
      name: 'X',
      email: 'x@x.test',
    });
    expect(viewer.customerId).toBe('ACME0001');
  });
});

describe('needsRefresh', () => {
  it('refreshes a token that expires in less than 2 minutes', () => {
    const now = 1_000_000;
    expect(
      needsRefresh(
        {idToken: '', refreshToken: '', expiresAt: now + 119_000},
        now,
      ),
    ).toBe(true);
    expect(
      needsRefresh(
        {idToken: '', refreshToken: '', expiresAt: now + 121_000},
        now,
      ),
    ).toBe(false);
  });
});
