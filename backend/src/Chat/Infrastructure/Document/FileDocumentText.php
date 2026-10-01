<?php

namespace App\Chat\Infrastructure\Document;

use App\Chat\Application\Document\DocumentText;
use App\Chat\Domain\Error\UnreadableFile;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

/**
 * The text of a chat attachment: a Word document (.docx, read from its word/document.xml: paragraphs, list items as
 * "- ", headings as "# ", table rows as "a | b"), a PDF (its text layer, smalot/pdfparser) or a text file (UTF-8, else
 * read as Windows-1252).
 */
final class FileDocumentText implements DocumentText
{
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    /** The unzipped document.xml is refused past this size (a zip bomb). */
    private const MAX_XML_BYTES = 64 * 1024 * 1024;

    public function read(string $extension, string $contents): string
    {
        return match ($extension) {
            'docx' => $this->word($contents),
            'pdf' => $this->pdf($contents),
            default => self::plain($contents),
        };
    }

    private function word(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'chat-docx');
        try {
            file_put_contents($path, $contents);
            $zip = new \ZipArchive();
            if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
                throw self::unreadable();
            }
            $stat = $zip->statName('word/document.xml');
            $xml = false !== $stat && $stat['size'] <= self::MAX_XML_BYTES ? $zip->getFromName('word/document.xml') : false;
            $zip->close();
        } finally {
            @unlink($path);
        }
        if (!\is_string($xml) || '' === $xml) {
            throw self::unreadable();
        }

        $document = new \DOMDocument();
        if (!@$document->loadXML($xml, \LIBXML_NONET | \LIBXML_COMPACT | \LIBXML_PARSEHUGE)) {
            throw self::unreadable();
        }
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', self::WORD_NS);
        $body = $xpath->query('/w:document/w:body')->item(0);
        if (null === $body) {
            throw self::unreadable();
        }

        $lines = [];
        foreach ($body->childNodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            if ('p' === $node->localName) {
                $lines[] = self::paragraph($xpath, $node);
            } elseif ('tbl' === $node->localName) {
                foreach ($xpath->query('w:tr', $node) as $row) {
                    $cells = [];
                    foreach ($xpath->query('w:tc', $row) as $cell) {
                        $cells[] = trim(implode(' ', array_map(static fn (\DOMElement $p): string => self::paragraph($xpath, $p), iterator_to_array($xpath->query('.//w:p', $cell)))));
                    }
                    $lines[] = implode(' | ', $cells);
                }
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /** A paragraph's text: its runs, tabs and breaks; "- " before a list item, "# " before a heading. */
    private static function paragraph(\DOMXPath $xpath, \DOMElement $paragraph): string
    {
        $text = '';
        foreach ($xpath->query('.//w:t | .//w:tab | .//w:br | .//w:cr', $paragraph) as $node) {
            $text .= match ($node->localName) {
                't' => $node->textContent,
                'tab' => ' ',
                default => "\n",
            };
        }
        $text = trim($text);
        if ('' === $text) {
            return '';
        }
        $style = strtolower((string) $xpath->evaluate('string(w:pPr/w:pStyle/@w:val)', $paragraph));
        if ('title' === $style || str_starts_with($style, 'heading') || str_starts_with($style, 'titulo') || str_starts_with($style, 'ttulo')) {
            return '# '.$text;
        }
        if ($xpath->query('w:pPr/w:numPr', $paragraph)->length > 0 || str_starts_with($style, 'list')) {
            return '- '.$text;
        }

        return $text;
    }

    private function pdf(string $contents): string
    {
        try {
            $config = new Config();
            $config->setRetainImageContent(false);

            return (new Parser([], $config))->parseContent($contents)->getText();
        } catch (\Throwable $e) {
            throw self::unreadable($e);
        }
    }

    private static function plain(string $contents): string
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        return mb_check_encoding($contents, 'UTF-8') ? $contents : (string) mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
    }

    private static function unreadable(?\Throwable $previous = null): UnreadableFile
    {
        return new UnreadableFile('FILE_UNREADABLE', 'The file could not be read: it may be damaged, protected with a password, or not of the type its name says.', [], $previous);
    }
}
