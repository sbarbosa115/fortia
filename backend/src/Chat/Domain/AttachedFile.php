<?php

namespace App\Chat\Domain;

use App\Chat\Domain\Error\UnreadableFile;

/**
 * A document the user attaches to a chat message to build a questionnaire from it (a Word file with 50 questions, a
 * PDF, a Markdown file…): its name and the text read from it. The client keeps it with the message it was attached to
 * and sends it back with every turn, like the draft (the backend keeps no chat state, PRD §7.19).
 *
 * - Readable types: docx, pdf, md, markdown, txt and csv; up to 10 MB each.
 * - The text read is 1 to 100,000 characters; a PDF without text (a scan) is refused.
 * - Up to 5 files in a conversation.
 *
 * What comes back from the client is untrusted: names and texts are capped here, whatever the client sends.
 */
final class AttachedFile
{
    public const EXTENSIONS = ['docx', 'pdf', 'md', 'markdown', 'txt', 'csv'];
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_CHARS = 100_000;
    public const MAX_FILES = 5;
    public const MAX_NAME = 255;

    private function __construct(
        public readonly string $filename,
        public readonly string $text,
    ) {
    }

    /**
     * The file the user uploads, before it is read: refused when its type cannot be read or it is too big.
     *
     * @return string its extension, lower case
     */
    public static function admit(string $filename, int $bytes): string
    {
        $extension = strtolower(pathinfo($filename, \PATHINFO_EXTENSION));
        if (!\in_array($extension, self::EXTENSIONS, true)) {
            throw new UnreadableFile('UNSUPPORTED_FILE_TYPE', 'doc' === $extension ? 'Save the document as .docx (Word 2007 or later) and attach it again.' : 'Attach a Word (.docx), PDF, Markdown, text or CSV file.');
        }
        if ($bytes <= 0) {
            throw new UnreadableFile('FILE_HAS_NO_TEXT', 'The file is empty.');
        }
        if ($bytes > self::MAX_BYTES) {
            throw new UnreadableFile('FILE_TOO_LARGE', 'The file is over 10 MB.');
        }

        return $extension;
    }

    /** The text read from an uploaded file: whitespace tidied, refused when there is none or too much. */
    public static function read(string $filename, string $text): self
    {
        $text = self::tidy($text);
        if ('' === $text) {
            throw new UnreadableFile('FILE_HAS_NO_TEXT', 'No text could be read from the file. If it is a scanned PDF, attach a version with text.');
        }
        if (mb_strlen($text) > self::MAX_CHARS) {
            throw new UnreadableFile('FILE_TOO_LONG', 'The file has more than 100,000 characters of text.');
        }

        return new self(self::name($filename), $text);
    }

    /**
     * The files of a message as the client sends them back: anything unexpected is dropped, every text capped.
     *
     * @return list<self>
     */
    public static function listFromArray(mixed $raw): array
    {
        $files = [];
        foreach (\is_array($raw) ? \array_slice(array_values($raw), 0, self::MAX_FILES) : [] as $file) {
            if (\is_array($file) && \is_string($file['filename'] ?? null) && \is_string($file['text'] ?? null) && '' !== trim($file['text'])) {
                $files[] = new self(self::name($file['filename']), mb_substr($file['text'], 0, self::MAX_CHARS));
            }
        }

        return $files;
    }

    /** @return array{filename: string, text: string} */
    public function toArray(): array
    {
        return ['filename' => $this->filename, 'text' => $this->text];
    }

    private static function name(string $filename): string
    {
        $name = trim(basename(str_replace('\\', '/', $filename)));

        return mb_substr('' === $name ? 'file' : $name, 0, self::MAX_NAME);
    }

    /** Line breaks unified, spaces collapsed inside a line, at most one blank line in a row. */
    private static function tidy(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}", "\f"], ["\n", "\n", ' ', "\n"], $text);
        $text = (string) preg_replace('/[^\S\n]+/u', ' ', $text);
        $text = (string) preg_replace('/ *\n */u', "\n", $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
