import {describe, expect, it} from 'vitest';
import {backToOf, withFrom} from './backTo';

describe('backTo', () => {
  it('goes back to a console page it knows, with its query string', () => {
    expect(backToOf('/assignations?search=acme')).toEqual({
      to: '/assignations?search=acme',
      kind: 'assignations',
    });
    const assignation = '/assignations/55d1c707-19cd-42bc-978c-038adb85eb95';
    expect(backToOf(`${assignation}?from=%2Fassignations`)).toEqual({
      to: `${assignation}?from=%2Fassignations`,
      kind: 'assignation',
    });
    expect(backToOf('/assignations')?.kind).toBe('assignations');
  });

  it('ignores any other place, so the query string cannot send the user elsewhere', () => {
    expect(backToOf(null)).toBeNull();
    expect(backToOf('https://evil.test')).toBeNull();
    expect(backToOf('//evil.test/assignations')).toBeNull();
    expect(backToOf('/assignations/x')).toBeNull();
    expect(backToOf('/settings')).toBeNull();
  });

  it('adds the way back to a link, keeping its own query', () => {
    expect(withFrom('/assignations/a', '/assignations?search=x')).toBe(
      '/assignations/a?from=%2Fassignations%3Fsearch%3Dx',
    );
    expect(withFrom('/a?tab=2', '/assignations')).toBe(
      '/a?tab=2&from=%2Fassignations',
    );
  });
});
