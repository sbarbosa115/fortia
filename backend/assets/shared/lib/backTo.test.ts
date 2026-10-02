import {describe, expect, it} from 'vitest';
import {backToOf, withFrom} from './backTo';

describe('backTo', () => {
  it('goes back to a console page it knows, with its query string', () => {
    expect(backToOf('/projects?search=acme')).toEqual({
      to: '/projects?search=acme',
      kind: 'projects',
    });
    const assignation = '/assignations/55d1c707-19cd-42bc-978c-038adb85eb95';
    expect(backToOf(`${assignation}?from=%2Fprojects`)).toEqual({
      to: `${assignation}?from=%2Fprojects`,
      kind: 'assignation',
    });
    expect(backToOf('/assignations')?.kind).toBe('assignations');
  });

  it('ignores any other place, so the query string cannot send the user elsewhere', () => {
    expect(backToOf(null)).toBeNull();
    expect(backToOf('https://evil.test')).toBeNull();
    expect(backToOf('//evil.test/projects')).toBeNull();
    expect(backToOf('/assignations/x')).toBeNull();
    expect(backToOf('/settings')).toBeNull();
  });

  it('adds the way back to a link, keeping its own query', () => {
    expect(withFrom('/assignations/a', '/projects?search=x')).toBe(
      '/assignations/a?from=%2Fprojects%3Fsearch%3Dx',
    );
    expect(withFrom('/a?tab=2', '/projects')).toBe('/a?tab=2&from=%2Fprojects');
  });
});
