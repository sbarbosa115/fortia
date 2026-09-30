/** The CSV template the console offers (PRD §10.10). */
export const TEMPLATE_FILENAME = 'organization_members_template.csv';

export const TEMPLATE_CSV = [
  'name,email,phone,role,area',
  'Ana Gómez,ana@example.com,+573001234567,Manager,Sales',
  'Bruno Díaz,,+573007654321,Analyst,Operations',
  '',
].join('\n');

/** Downloads the template as a UTF-8 file (with a BOM, so spreadsheet apps read the accents). */
export function downloadTemplate(): void {
  const blob = new Blob(['\uFEFF', TEMPLATE_CSV], {
    type: 'text/csv;charset=utf-8',
  });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = TEMPLATE_FILENAME;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 0);
}
