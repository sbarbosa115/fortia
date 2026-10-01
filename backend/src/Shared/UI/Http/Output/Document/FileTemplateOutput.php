<?php

namespace App\Shared\UI\Http\Output\Document;

/** The template of a file question: the file the respondent downloads, fills in and uploads (FileTemplate). */
final class FileTemplateOutput
{
    public function __construct(
        public readonly string $key,
        public readonly string $filename,
    ) {
    }

    public static function fromArray(mixed $t): ?self
    {
        return \is_array($t) && \is_string($t['key'] ?? null) ? new self($t['key'], (string) ($t['filename'] ?? basename($t['key']))) : null;
    }
}
