<?php

namespace App\Shared\Domain\Document;

/**
 * The template of a file question: a file the respondent downloads, fills in and uploads back. The control carries
 * `template: {key, filename}`, the key in object storage being `templates/{customer_id}/{uuid}/{filename}` (so the
 * download keeps the file's name).
 *
 * A save may instead send the template's contents as text (`{filename, text}`, what the chat writes as a CSV): the
 * save stores it and keeps the key, as it does with a chain prompt's text.
 */
final class FileTemplate
{
    public const TEXT_MAX = 100_000;
    public const FILENAME_MAX = 100;
    public const MAX_BYTES = 20 * 1024 * 1024;
    private const KEY_PATTERN = '#^templates/([A-Za-z0-9_-]{1,64})/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/([A-Za-z0-9._ -]{1,100})$#';

    /**
     * A control's template in the stored shape: {key, filename}, {filename, text} (to be stored), or null.
     *
     * @return array{key: string, filename: string}|array{filename: string, text: string}|null
     */
    public static function normalize(mixed $raw): ?array
    {
        if (!\is_array($raw)) {
            return null;
        }
        $key = \is_string($raw['key'] ?? null) ? trim($raw['key']) : '';
        $text = \is_string($raw['text'] ?? null) ? mb_substr($raw['text'], 0, self::TEXT_MAX) : '';
        if ('' !== $key) {
            if (!self::isKey($key)) {
                return null;
            }

            return ['key' => $key, 'filename' => self::filename($raw['filename'] ?? null, basename($key))];
        }
        if ('' === trim($text)) {
            return null;
        }

        return ['filename' => self::filename($raw['filename'] ?? null, 'template.csv'), 'text' => $text];
    }

    /** templates/{customer_id}/{uuid}/{filename}: nothing else is a template's key. */
    public static function isKey(string $key): bool
    {
        return 1 === preg_match(self::KEY_PATTERN, $key) && !str_contains($key, '..');
    }

    /** The account a template's key belongs to (null when it is not a template's key). */
    public static function customerOf(string $key): ?string
    {
        return 1 === preg_match(self::KEY_PATTERN, $key, $m) && !str_contains($key, '..') ? $m[1] : null;
    }

    /** The key a new template of this account is stored at. */
    public static function keyFor(string $customerId, string $uuid, string $filename): string
    {
        return 'templates/'.$customerId.'/'.$uuid.'/'.self::filename($filename, 'template');
    }

    /** A file name safe in a key and a download: letters, digits, ".", "_", "-" and spaces; at most 100 characters. */
    public static function filename(mixed $name, string $fallback): string
    {
        $name = \is_string($name) ? basename(str_replace('\\', '/', $name)) : '';
        $ascii = (string) (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: '');
        $clean = trim((string) preg_replace('/[^A-Za-z0-9._ -]+/', '-', $ascii), ' .-');
        $clean = (string) preg_replace('/-{2,}/', '-', $clean);
        if ('' === $clean) {
            $clean = $fallback;
        }
        if (\strlen($clean) > self::FILENAME_MAX) {
            $extension = pathinfo($clean, \PATHINFO_EXTENSION);
            $suffix = '' !== $extension && \strlen($extension) <= 10 ? '.'.$extension : '';
            $clean = rtrim(substr($clean, 0, self::FILENAME_MAX - \strlen($suffix)), ' .-').$suffix;
        }

        return $clean;
    }

    /**
     * A CSV with a header row and example rows (what the chat's template turns into). Excel opens it with the BOM.
     *
     * @param list<string>       $columns
     * @param list<list<string>> $rows
     */
    public static function csv(array $columns, array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        \assert(false !== $out);
        fwrite($out, "\u{FEFF}");
        fputcsv($out, $columns, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, \array_slice(array_pad($row, \count($columns), ''), 0, \count($columns)), ',', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
