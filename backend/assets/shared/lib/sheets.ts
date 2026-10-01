/**
 * Export answers to a Google Sheet from the browser (PRD §13.9): OAuth with the drive.file scope (only the files
 * this app creates), one sheet per export key ("questionnaireId|assignationId", kept in the file's appProperties
 * as skylineExportKey) that is cleared and rewritten, or created the first time; then it opens in a new tab.
 *
 * The rows are built by pure functions (buildSheetRows, sheetCell) so the columns can be tested; the Google calls
 * need GOOGLE_SHEETS_CLIENT_ID (appConfig().googleSheetsClientId): without it the export is disabled.
 */

export const SHEETS_SCOPE = 'https://www.googleapis.com/auth/drive.file';
export const EXPORT_KEY_PROPERTY = 'skylineExportKey';

/** The parts of a session question the sheet reads (the API's QuestionOutput is one). */
export type SheetQuestion = {
  id: string;
  title: string;
  options: Array<{
    type: string;
    value?: unknown;
    skipped?: boolean | null;
    options?: Array<{label: string; value?: unknown}>;
  }>;
};

export type SheetSession = {
  started_at?: string | null;
  user_data?: {name?: string; email?: string; phone?: string} | null;
  member?: {
    name?: string | null;
    email?: string | null;
    phone?: string | null;
  } | null;
  questions: SheetQuestion[];
};

/** The texts of the sheet, in the console's language. */
export type SheetLabels = {
  startedAt: string;
  user: string;
  email: string;
  phone: string;
  skipped: string;
  /** "File uploaded" for 1, "N files uploaded" for more. */
  filesUploaded: (count: number) => string;
};

/** The export key of a questionnaire's answers, or of an assignation's. */
export function exportKey(
  questionnaireId: string,
  assignationId?: string | null,
): string {
  return `${questionnaireId}|${assignationId ?? ''}`;
}

function present(value: unknown): boolean {
  return value !== null && value !== undefined && String(value).trim() !== '';
}

/** One answer cell: "Skipped", "File uploaded" / "N files uploaded", the option labels, the text, or empty. */
export function sheetCell(
  question: SheetQuestion,
  labels: SheetLabels,
): string {
  const control = question.options[0];
  if (!control || control.type === 'message') {
    return '';
  }
  if (control.skipped) {
    return labels.skipped;
  }
  const values = (
    Array.isArray(control.value) ? control.value : [control.value]
  ).filter(present);
  if (values.length === 0) {
    return '';
  }
  if (control.type === 'file') {
    return labels.filesUploaded(values.length);
  }
  return values
    .map((value) => {
      const option = control.options?.find(
        (o) => String(o.value ?? o.label) === String(value),
      );
      return option?.label ?? String(value);
    })
    .join(', ');
}

/**
 * Header + one row per session: Started At, User, Email, Phone (only when some session has one), then one column
 * per question in the order of the first session (message slides left out).
 */
export function buildSheetRows(
  sessions: SheetSession[],
  labels: SheetLabels,
  formatDate: (iso: string) => string = (iso) => iso,
): string[][] {
  const columns: {id: string; title: string}[] = [];
  const seen = new Set<string>();
  for (const session of sessions) {
    for (const question of session.questions) {
      if (!seen.has(question.id) && question.options[0]?.type !== 'message') {
        seen.add(question.id);
        columns.push({id: question.id, title: question.title});
      }
    }
  }
  const who = (s: SheetSession) => ({
    name: s.user_data?.name || s.member?.name || '',
    email: s.user_data?.email || s.member?.email || '',
    phone: s.user_data?.phone || s.member?.phone || '',
  });
  const withPhone = sessions.some((s) => who(s).phone !== '');
  const header = [
    labels.startedAt,
    labels.user,
    labels.email,
    ...(withPhone ? [labels.phone] : []),
    ...columns.map((c) => c.title),
  ];
  const rows = sessions.map((session) => {
    const person = who(session);
    const byId = new Map(session.questions.map((q) => [q.id, q]));
    return [
      session.started_at ? formatDate(session.started_at) : '',
      person.name,
      person.email,
      ...(withPhone ? [person.phone] : []),
      ...columns.map((c) => {
        const question = byId.get(c.id);
        return question ? sheetCell(question, labels) : '';
      }),
    ];
  });
  return [header, ...rows];
}

type TokenResponse = {access_token?: string; error?: string};
type TokenClient = {requestAccessToken: (options?: {prompt?: string}) => void};
/** Google's names (snake_case) for the token client's callbacks. */
type TokenErrorCallback = (error: {type?: string}) => void;
type GoogleIdentity = {
  accounts: {
    oauth2: {
      initTokenClient: (config: {
        client_id: string;
        scope: string;
        callback: (response: TokenResponse) => void;
        error_callback?: TokenErrorCallback;
      }) => TokenClient;
    };
  };
};

declare global {
  interface Window {
    google?: GoogleIdentity;
  }
}

let loading: Promise<GoogleIdentity> | null = null;

function loadIdentityServices(): Promise<GoogleIdentity> {
  if (window.google?.accounts?.oauth2) {
    return Promise.resolve(window.google);
  }
  loading ??= new Promise<GoogleIdentity>((resolve, reject) => {
    const script = document.createElement('script');
    script.src = 'https://accounts.google.com/gsi/client';
    script.async = true;
    script.onload = () =>
      window.google
        ? resolve(window.google)
        : reject(new Error('Google Identity Services did not load.'));
    script.onerror = () => {
      loading = null;
      reject(new Error('Google Identity Services did not load.'));
    };
    document.head.appendChild(script);
  });
  return loading;
}

function accessToken(clientId: string): Promise<string> {
  return loadIdentityServices().then(
    (google) =>
      new Promise<string>((resolve, reject) => {
        const client = google.accounts.oauth2.initTokenClient({
          client_id: clientId,
          scope: SHEETS_SCOPE,
          callback: (response) =>
            response.access_token
              ? resolve(response.access_token)
              : reject(new Error(response.error ?? 'access_denied')),
          error_callback: (error) =>
            reject(new Error(error.type ?? 'popup_closed')),
        });
        client.requestAccessToken();
      }),
  );
}

async function google<T>(
  token: string,
  url: string,
  init: {method?: string; body?: unknown} = {},
): Promise<T> {
  const response = await fetch(url, {
    method: init.method ?? 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: init.body === undefined ? undefined : JSON.stringify(init.body),
  });
  if (!response.ok) {
    throw new Error(`Google API ${response.status}`);
  }
  return (await response.json()) as T;
}

/**
 * Writes the rows to the export's sheet (created, or cleared and rewritten) and returns its URL.
 */
export async function exportToSheets(options: {
  clientId: string;
  key: string;
  title: string;
  rows: string[][];
}): Promise<string> {
  const token = await accessToken(options.clientId);
  const query = encodeURIComponent(
    `appProperties has { key='${EXPORT_KEY_PROPERTY}' and value='${options.key.replace(/'/g, "\\'")}' } and trashed = false`,
  );
  const found = await google<{files?: {id: string}[]}>(
    token,
    `https://www.googleapis.com/drive/v3/files?q=${query}&fields=files(id)`,
  );
  let id = found.files?.[0]?.id;
  if (id) {
    await google(
      token,
      `https://sheets.googleapis.com/v4/spreadsheets/${id}/values:batchClear`,
      {
        method: 'POST',
        body: {ranges: ['A:ZZZ']},
      },
    );
    await google(
      token,
      `https://sheets.googleapis.com/v4/spreadsheets/${id}:batchUpdate`,
      {
        method: 'POST',
        body: {
          requests: [
            {
              updateSpreadsheetProperties: {
                properties: {title: options.title},
                fields: 'title',
              },
            },
          ],
        },
      },
    );
  } else {
    const created = await google<{spreadsheetId: string}>(
      token,
      'https://sheets.googleapis.com/v4/spreadsheets',
      {method: 'POST', body: {properties: {title: options.title}}},
    );
    id = created.spreadsheetId;
    await google(token, `https://www.googleapis.com/drive/v3/files/${id}`, {
      method: 'PATCH',
      body: {appProperties: {[EXPORT_KEY_PROPERTY]: options.key}},
    });
  }
  await google(
    token,
    `https://sheets.googleapis.com/v4/spreadsheets/${id}/values/A1?valueInputOption=RAW`,
    {method: 'PUT', body: {values: options.rows}},
  );
  return `https://docs.google.com/spreadsheets/d/${id}/edit`;
}
