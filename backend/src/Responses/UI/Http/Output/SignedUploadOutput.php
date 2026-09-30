<?php

namespace App\Responses\UI\Http\Output;

use App\Shared\Application\Storage\SignedUpload;
use OpenApi\Attributes as OA;

/**
 * A signed upload (PRD §8.4 POST /signed-urls): for an answer's file, a form upload (POST multipart with `fields`
 * plus `file`); for a prompt, a direct PUT to `url` (no fields). `key` is what the session stores.
 */
final class SignedUploadOutput
{
    /** @param array<string, string>|null $fields */
    public function __construct(
        public readonly string $url,
        public readonly string $key,
        public readonly int $expires_in,
        #[OA\Property(type: 'object', nullable: true, additionalProperties: new OA\AdditionalProperties(type: 'string'))]
        public readonly ?array $fields = null,
    ) {
    }

    public static function form(SignedUpload $upload): self
    {
        return new self($upload->url, $upload->key, $upload->expiresIn, $upload->fields);
    }

    public static function put(SignedUpload $upload): self
    {
        return new self($upload->url, $upload->key, $upload->expiresIn);
    }
}
