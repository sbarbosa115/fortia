import type {FollowUpAnswer, Respondent} from '@console/entities/assignation';

/** A CSV cell: quoted when it holds a comma, a quote or a line break (quotes doubled). */
function cell(value: string | number): string {
  const text = String(value);
  return /[",\r\n;]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}

/**
 * The respondents as CSV (PRD §10.11 "CSV: columns Name, Email, Attempts, Status"), header first, CRLF line ends.
 * $status gives the translated status.
 */
export function respondentsCsv(
  rows: Respondent[],
  header: [string, string, string, string],
  status: (row: Respondent) => string,
): string {
  const lines = [header.map(cell).join(',')];
  for (const row of rows) {
    lines.push(
      [
        row.organization_user_name,
        row.organization_user_email ?? '',
        row.attempts,
        status(row),
      ]
        .map(cell)
        .join(','),
    );
  }
  return lines.join('\r\n') + '\r\n';
}

/** "{name}.csv" without the characters a file name cannot hold. */
export function csvFileName(name: string): string {
  const safe = name
    .replace(/[\\/:*?"<>|]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
  return `${safe || 'assignation'}.csv`;
}

/** The answers still waiting for a decision in this attempt (a locked one is already approved). */
export function unreviewed(answers: FollowUpAnswer[]): FollowUpAnswer[] {
  return answers.filter((answer) => answer.review_state === 'not_reviewed');
}

export function rejected(answers: FollowUpAnswer[]): FollowUpAnswer[] {
  return answers.filter((answer) => answer.review_state === 'rejected');
}

/**
 * Where the review dialog goes after a decision on $index (PRD §10.11 "After deciding, it jumps to the next
 * unreviewed one"): the next one not reviewed after it, wrapping around; null when every answer is reviewed.
 */
export function nextUnreviewed(
  answers: FollowUpAnswer[],
  index: number,
): number | null {
  for (let step = 1; step <= answers.length; step += 1) {
    const candidate = (index + step) % answers.length;
    if (answers[candidate]?.review_state === 'not_reviewed') {
      return candidate;
    }
  }
  return null;
}
