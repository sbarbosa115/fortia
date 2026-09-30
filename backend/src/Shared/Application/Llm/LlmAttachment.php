<?php

namespace App\Shared\Application\Llm;

/**
 * A file given to the model (prompt chains, PRD §7.8): pdf, an image (png, jpg, webp, gif) or text.
 */
final class LlmAttachment
{
    public function __construct(
        public readonly string $filename,
        public readonly string $mediaType,
        public readonly string $contents,
    ) {
    }

    public function isPdf(): bool
    {
        return 'application/pdf' === $this->mediaType;
    }

    public function isImage(): bool
    {
        return \in_array($this->mediaType, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true);
    }
}
