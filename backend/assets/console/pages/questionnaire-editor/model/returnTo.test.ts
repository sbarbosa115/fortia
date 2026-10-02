import {describe, expect, it} from 'vitest';
import {returnToOf, returnToSearch} from './returnTo';

describe('returnTo', () => {
  it('goes back to the projects or to an assignation', () => {
    expect(returnToOf('/projects')).toEqual({
      to: '/projects',
      kind: 'projects',
    });
    const assignation = '/assignations/55d1c707-19cd-42bc-978c-038adb85eb95';
    expect(returnToOf(assignation)).toEqual({
      to: assignation,
      kind: 'assignation',
    });
  });

  it('ignores any other place, so the query string cannot send the user elsewhere', () => {
    expect(returnToOf(null)).toBeNull();
    expect(returnToOf('https://evil.test')).toBeNull();
    expect(returnToOf('//evil.test/projects')).toBeNull();
    expect(returnToOf('/assignations/x')).toBeNull();
  });

  it('carries itself along in the query string', () => {
    expect(returnToSearch({to: '/projects', kind: 'projects'})).toBe(
      '?from=%2Fprojects',
    );
    expect(returnToSearch(null)).toBe('');
  });
});
