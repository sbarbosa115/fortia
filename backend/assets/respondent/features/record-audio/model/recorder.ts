/**
 * The rules of a voice answer (PRD §9.7): which message a recording error shows, how the timer reads, how
 * recordings make up the answer.
 */
export type RecorderErrorKey =
  | 'insecure'
  | 'unsupported'
  | 'noMicrophone'
  | 'busy'
  | 'access'
  | 'denied'
  | 'connection';

/** Thrown by the recorder with the case of §9.7's error table. */
export class RecorderError extends Error {
  constructor(public readonly key: RecorderErrorKey) {
    super(key);
    this.name = 'RecorderError';
  }
}

/**
 * The §9.7 case of a failure to start recording: the browser's media errors by name; anything else is a connection
 * problem.
 */
export function recorderErrorKey(error: unknown): RecorderErrorKey {
  if (error instanceof RecorderError) {
    return error.key;
  }
  const name = (error as {name?: unknown} | null)?.name;
  switch (name) {
    case 'NotAllowedError':
    case 'PermissionDeniedError':
    case 'SecurityError':
      return 'denied';
    case 'NotFoundError':
    case 'DevicesNotFoundError':
    case 'OverconstrainedError':
      return 'noMicrophone';
    case 'NotReadableError':
    case 'TrackStartError':
      return 'busy';
    case 'AbortError':
    case 'TypeError':
      return 'access';
    default:
      return 'connection';
  }
}

/** "Listening... mm:ss". */
export function formatElapsed(ms: number): string {
  const seconds = Math.max(0, Math.floor(ms / 1000));
  const mm = String(Math.floor(seconds / 60)).padStart(2, '0');
  const ss = String(seconds % 60).padStart(2, '0');
  return `${mm}:${ss}`;
}

/** The answer's recordings: a new one is added, a re-recorded one replaces its segment (§9.7). */
export function withSegment(
  segments: string[],
  text: string,
  replaceIndex: number | null,
): string[] {
  if (replaceIndex !== null && replaceIndex < segments.length) {
    return segments.map((segment, index) =>
      index === replaceIndex ? text : segment,
    );
  }
  return [...segments, text];
}

/** The 9 wave bars from the microphone level (0–1), louder in the middle. */
export function waveBars(level: number): number[] {
  const shape = [0.35, 0.55, 0.75, 0.9, 1, 0.9, 0.75, 0.55, 0.35];
  const clamped = Math.min(1, Math.max(0, level));
  return shape.map((weight) => 0.12 + 0.88 * clamped * weight);
}
