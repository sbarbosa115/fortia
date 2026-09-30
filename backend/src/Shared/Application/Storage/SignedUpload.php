<?php

namespace App\Shared\Application\Storage;

final class SignedUpload
{
    /**
     * @param array<string, string> $fields form fields to send with the file (form-style uploads only)
     */
    public function __construct(
        public readonly string $url,
        public readonly string $key,
        public readonly int $expiresIn,
        public readonly array $fields = [],
    ) {
    }
}
