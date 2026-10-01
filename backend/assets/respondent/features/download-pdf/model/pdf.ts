/**
 * The results report in PDF, generated in the browser (PRD §9.12 "PDF: client-side or server-side"): file
 * `YourResults.pdf`, with the brand's primary color as the accent.
 */
export type PdfSection =
  | {kind: 'text'; heading: string; lines: string[]}
  | {
      kind: 'bars';
      heading: string;
      rows: {label: string; value: string; pct: number}[];
    }
  | {kind: 'list'; heading: string; items: string[]; numbered: boolean};

export type PdfReport = {
  title: string;
  subtitle?: string;
  sections: PdfSection[];
  footer?: string;
};

export const PDF_FILE_NAME = 'YourResults.pdf';

/** The accent as RGB: the brand's primary color when it is a hex, else the default near black. */
export function accentRgb(
  color: string | null | undefined,
): [number, number, number] {
  const hex = (color ?? '').trim().replace('#', '');
  const full =
    hex.length === 3 || hex.length === 4
      ? hex
          .slice(0, 3)
          .split('')
          .map((c) => c + c)
          .join('')
      : hex.slice(0, 6);
  if (!/^[0-9a-f]{6}$/i.test(full)) {
    return [24, 24, 27];
  }
  return [0, 2, 4].map((i) => parseInt(full.slice(i, i + 2), 16)) as [
    number,
    number,
    number,
  ];
}

/** The brand's primary color as the page applied it (the --color-primary token). */
export function currentAccent(): string {
  return getComputedStyle(document.documentElement)
    .getPropertyValue('--color-primary')
    .trim();
}

/** Builds and downloads the report (jsPDF is loaded only when asked for). */
export async function downloadReport(report: PdfReport): Promise<void> {
  const {jsPDF: JsPdf} = await import('jspdf');
  const doc = new JsPdf({unit: 'pt', format: 'a4'});
  const [r, g, b] = accentRgb(currentAccent());
  const width = doc.internal.pageSize.getWidth();
  const height = doc.internal.pageSize.getHeight();
  const margin = 48;
  const usable = width - margin * 2;
  let y = margin;

  const ensure = (space: number) => {
    if (y + space > height - margin) {
      doc.addPage();
      y = margin;
    }
  };
  const write = (
    text: string,
    size: number,
    bold = false,
    color = [24, 24, 27],
  ) => {
    doc.setFont('helvetica', bold ? 'bold' : 'normal');
    doc.setFontSize(size);
    doc.setTextColor(color[0]!, color[1]!, color[2]!);
    for (const line of doc.splitTextToSize(text, usable) as string[]) {
      ensure(size * 1.4);
      doc.text(line, margin, y);
      y += size * 1.4;
    }
  };

  doc.setFillColor(r, g, b);
  doc.rect(0, 0, width, 8, 'F');
  write(report.title, 22, true);
  if (report.subtitle) {
    write(report.subtitle, 12, false, [82, 82, 91]);
  }
  y += 12;

  for (const section of report.sections) {
    ensure(40);
    write(section.heading, 14, true, [r, g, b]);
    y += 4;
    if (section.kind === 'text') {
      section.lines.forEach((line) => write(line, 11));
    } else if (section.kind === 'list') {
      section.items.forEach((item, index) =>
        write(`${section.numbered ? `${index + 1}.` : '•'} ${item}`, 11),
      );
    } else {
      for (const row of section.rows) {
        ensure(30);
        write(`${row.label} — ${row.value}`, 11);
        doc.setFillColor(228, 228, 231);
        doc.rect(margin, y - 6, usable, 6, 'F');
        doc.setFillColor(r, g, b);
        doc.rect(
          margin,
          y - 6,
          (usable * Math.min(100, Math.max(0, row.pct))) / 100,
          6,
          'F',
        );
        y += 10;
      }
    }
    y += 12;
  }
  if (report.footer) {
    write(report.footer, 9, false, [113, 113, 122]);
  }
  doc.save(PDF_FILE_NAME);
}
