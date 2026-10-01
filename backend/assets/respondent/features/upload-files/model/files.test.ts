import {describe, expect, it} from 'vitest';
import {
  acceptFiles,
  keysOf,
  MAX_FILE_BYTES,
  namePasted,
  screenshotName,
  uploadsFromKeys,
} from './files';

function file(name: string, size = 10, type = 'application/pdf'): File {
  const f = new File(['x'], name, {type});
  Object.defineProperty(f, 'size', {value: size});
  return f;
}

describe('file answers (PRD §9.9)', () => {
  it('discards files over 500 MB and beyond the account limit', () => {
    const result = acceptFiles(
      8,
      [file('a'), file('big', MAX_FILE_BYTES + 1), file('b'), file('c')],
      10,
    );
    expect(result.accepted.map((f) => f.name)).toEqual(['a', 'b']);
    expect(result.tooLarge).toBe(1);
    expect(result.discarded).toBe(1);
  });

  it('accepts a file of exactly 500 MB', () => {
    expect(
      acceptFiles(0, [file('a', MAX_FILE_BYTES)], 10).accepted,
    ).toHaveLength(1);
  });

  it('renames pasted screenshots screenshot-YYYYMMDD-HHmmss[-n]', () => {
    const date = new Date(2026, 8, 30, 9, 5, 7);
    expect(screenshotName(date, 'png')).toBe('screenshot-20260930-090507.png');
    expect(screenshotName(date, 'png', 1)).toBe(
      'screenshot-20260930-090507-1.png',
    );
    const named = namePasted(
      [
        file('image.png', 1, 'image/png'),
        file('image.png', 1, 'image/jpeg'),
        file('notes.txt', 1, 'text/plain'),
      ],
      date,
    );
    expect(named.map((f) => f.name)).toEqual([
      'screenshot-20260930-090507.png',
      'screenshot-20260930-090507-1.jpg',
      'notes.txt',
    ]);
  });

  it('saves the object keys, not URLs', () => {
    const uploads = uploadsFromKeys(['ACME/s/q/abc.pdf']);
    expect(uploads[0]?.name).toBe('abc.pdf');
    expect(
      keysOf([
        ...uploads,
        {...uploads[0]!, id: 'x', status: 'uploading', key: null},
      ]),
    ).toEqual(['ACME/s/q/abc.pdf']);
  });
});
