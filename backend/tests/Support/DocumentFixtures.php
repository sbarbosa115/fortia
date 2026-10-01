<?php

namespace App\Tests\Support;

/**
 * Real documents built in memory for the tests of the chat's attached files: a Word document (.docx) and a PDF with a
 * text layer.
 */
trait DocumentFixtures
{
    /**
     * A .docx whose paragraphs are the given lines; a line starting with "# " is a Heading1, one with "- " a list item.
     *
     * @param list<string>       $lines
     * @param list<list<string>> $table rows of a table after the paragraphs
     */
    private static function docx(array $lines, array $table = []): string
    {
        $paragraphs = '';
        foreach ($lines as $line) {
            $props = '';
            if (str_starts_with($line, '# ')) {
                [$props, $line] = ['<w:pPr><w:pStyle w:val="Heading1"/></w:pPr>', substr($line, 2)];
            } elseif (str_starts_with($line, '- ')) {
                [$props, $line] = ['<w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>', substr($line, 2)];
            }
            // Word splits a paragraph into several runs: the reader must join them.
            $half = intdiv(mb_strlen($line), 2);
            $paragraphs .= '<w:p>'.$props.'<w:r><w:t xml:space="preserve">'.htmlspecialchars(mb_substr($line, 0, $half)).'</w:t></w:r><w:r><w:t>'.htmlspecialchars(mb_substr($line, $half)).'</w:t></w:r></w:p>';
        }
        if ([] !== $table) {
            $paragraphs .= '<w:tbl>'.implode('', array_map(static fn (array $row): string => '<w:tr>'.implode('', array_map(static fn (string $cell): string => '<w:tc><w:p><w:r><w:t>'.htmlspecialchars($cell).'</w:t></w:r></w:p></w:tc>', $row)).'</w:tr>', $table)).'</w:tbl>';
        }
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$paragraphs.'</w:body></w:document>';

        $path = (string) tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', $document);
        $zip->close();
        $contents = (string) file_get_contents($path);
        unlink($path);

        return $contents;
    }

    /**
     * A one-page PDF (Helvetica, ASCII text) with one line of text per line given.
     *
     * @param list<string> $lines
     */
    private static function pdf(array $lines): string
    {
        $stream = "BT /F1 11 Tf 50 780 Td 14 TL\n";
        foreach ($lines as $line) {
            $stream .= '('.strtr($line, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']).") Tj T*\n";
        }
        $stream .= 'ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.\strlen($stream)." >>\nstream\n".$stream."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = \strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = \strlen($pdf);
        $pdf .= 'xref'."\n0 ".(\count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= \sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(\count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }
}
