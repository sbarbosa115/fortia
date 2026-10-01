/** Any file type, up to 500 MB each (PRD §9.9). */
export const MAX_FILE_BYTES = 500 * 1024 * 1024;

export type Upload = {
  id: string;
  name: string;
  status: 'uploading' | 'done' | 'error';
  progress: number;
  /** The object key: the saved value (§9.9), once uploaded. */
  key: string | null;
  file: File | null;
};

/**
 * Which of the files chosen, dropped or pasted are added (§9.9): none over 500 MB, and no more than the account's
 * max_files in total; the rest are discarded and counted.
 */
export function acceptFiles(
  current: number,
  incoming: File[],
  max: number,
): {accepted: File[]; tooLarge: number; discarded: number} {
  const fitting = incoming.filter((file) => file.size <= MAX_FILE_BYTES);
  const room = Math.max(0, max - current);
  return {
    accepted: fitting.slice(0, room),
    tooLarge: incoming.length - fitting.length,
    discarded: Math.max(0, fitting.length - room),
  };
}

function pad(value: number): string {
  return String(value).padStart(2, '0');
}

/** A pasted screenshot is renamed `screenshot-YYYYMMDD-HHmmss[-n].{ext}` (§9.9). */
export function screenshotName(date: Date, extension: string, n = 0): string {
  const stamp =
    `${date.getFullYear()}${pad(date.getMonth() + 1)}${pad(date.getDate())}-` +
    `${pad(date.getHours())}${pad(date.getMinutes())}${pad(date.getSeconds())}`;
  return `screenshot-${stamp}${n > 0 ? `-${n}` : ''}.${extension}`;
}

/** Pasted images get a screenshot name; other pasted files keep theirs. */
export function namePasted(files: File[], date: Date): File[] {
  let n = 0;
  return files.map((file) => {
    if (!file.type.startsWith('image/')) {
      return file;
    }
    const extension = file.type.split('/')[1]?.replace('jpeg', 'jpg') || 'png';
    const renamed = new File([file], screenshotName(date, extension, n), {
      type: file.type,
    });
    n += 1;
    return renamed;
  });
}

/** The uploads of a saved answer: its keys, already uploaded. */
export function uploadsFromKeys(value: unknown): Upload[] {
  if (!Array.isArray(value)) {
    return [];
  }
  return value
    .filter((key): key is string => typeof key === 'string' && key !== '')
    .map((key) => ({
      id: key,
      name: key.split('/').pop() ?? key,
      status: 'done',
      progress: 100,
      key,
      file: null,
    }));
}

/** The saved value: the keys of the files uploaded. */
export function keysOf(uploads: Upload[]): string[] {
  return uploads
    .filter((upload) => upload.status === 'done' && upload.key !== null)
    .map((upload) => upload.key as string);
}
