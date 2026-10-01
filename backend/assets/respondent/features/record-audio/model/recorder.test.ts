import {describe, expect, it} from 'vitest';
import {
  formatElapsed,
  RecorderError,
  recorderErrorKey,
  waveBars,
  withSegment,
} from './recorder';

describe('voice answers (PRD §9.7)', () => {
  it.each([
    [{name: 'NotAllowedError'}, 'denied'],
    [{name: 'NotFoundError'}, 'noMicrophone'],
    [{name: 'NotReadableError'}, 'busy'],
    [{name: 'AbortError'}, 'access'],
    [new Error('socket closed'), 'connection'],
    [new RecorderError('insecure'), 'insecure'],
    [new RecorderError('unsupported'), 'unsupported'],
  ])('maps %o to the "%s" message', (error, key) => {
    expect(recorderErrorKey(error)).toBe(key);
  });

  it('reads the timer as mm:ss', () => {
    expect(formatElapsed(0)).toBe('00:00');
    expect(formatElapsed(75_400)).toBe('01:15');
  });

  it('adds a recording, or replaces the one recorded again', () => {
    expect(withSegment(['a'], 'b', null)).toEqual(['a', 'b']);
    expect(withSegment(['a', 'b'], 'B', 1)).toEqual(['a', 'B']);
  });

  it('draws 9 bars following the level', () => {
    expect(waveBars(0)).toHaveLength(9);
    expect(waveBars(1)[4]).toBe(1);
    expect(waveBars(0)[4]).toBeCloseTo(0.12);
  });
});
