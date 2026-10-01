<?php

namespace App\Generation\Domain;

/**
 * The files attached to a stage's answers that go to the model (PRD §7.8): up to 5 per stage and 32 MB in total;
 * a pdf up to 32 MB, an image (png, jpg, jpeg, webp, gif) up to 20 MB, a text file (txt, md, csv, json) up to
 * 256 KB and 100,000 characters. A file of another type, or over its limit, is left out; the stage is still
 * generated from the answers.
 */
final class AttachmentBudget
{
    public const MAX_FILES = 5;
    public const MAX_TOTAL_BYTES = 32 * 1024 * 1024;
    public const MAX_PDF_BYTES = 32 * 1024 * 1024;
    public const MAX_IMAGE_BYTES = 20 * 1024 * 1024;
    public const MAX_TEXT_BYTES = 256 * 1024;
    public const MAX_TEXT_CHARS = 100_000;

    private const MEDIA_TYPES = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'txt' => 'text/plain',
        'md' => 'text/markdown',
        'csv' => 'text/csv',
        'json' => 'application/json',
    ];

    private int $files = 0;
    private int $bytes = 0;

    /** The media type of an allowed file, by its extension; null when the type is not allowed. */
    public static function mediaType(string $filename): ?string
    {
        $extension = strtolower(pathinfo($filename, \PATHINFO_EXTENSION));

        return self::MEDIA_TYPES[$extension] ?? null;
    }

    public function isFull(): bool
    {
        return $this->files >= self::MAX_FILES;
    }

    /**
     * Takes the file if it fits: its type is allowed, it is within its own limit and within what is left of the
     * stage's. Returns its media type, or null when it is left out.
     */
    public function admit(string $filename, string $contents): ?string
    {
        $type = self::mediaType($filename);
        $size = \strlen($contents);
        if (null === $type || 0 === $size || $this->isFull() || $this->bytes + $size > self::MAX_TOTAL_BYTES) {
            return null;
        }
        $fits = match (true) {
            'application/pdf' === $type => $size <= self::MAX_PDF_BYTES,
            str_starts_with($type, 'image/') => $size <= self::MAX_IMAGE_BYTES,
            default => $size <= self::MAX_TEXT_BYTES && mb_check_encoding($contents, 'UTF-8') && mb_strlen($contents) <= self::MAX_TEXT_CHARS,
        };
        if (!$fits) {
            return null;
        }
        ++$this->files;
        $this->bytes += $size;

        return $type;
    }
}
