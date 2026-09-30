<?php

namespace App\Shared\Application\Storage;

/**
 * Object storage (PRD §13.5). Keys keep the PRD's structure ({customer_id}/{session_id}/{question_id}/{md5}{ext}
 * for answer files, prompts/{customer_id}/{uuid}.txt for prompt texts). Signed URLs last 15 minutes.
 */
interface ObjectStorage
{
    public const SIGNED_URL_TTL = 900;

    public function put(string $key, string $contents, string $contentType = 'application/octet-stream'): void;

    /** @throws ObjectNotFound */
    public function get(string $key): string;

    public function exists(string $key): bool;

    public function delete(string $key): void;

    /**
     * A form-style signed upload (POST multipart with the returned fields plus "file").
     *
     * @param int $maxBytes the largest file the signature accepts
     */
    public function signedUploadForm(string $key, string $contentType, int $minBytes, int $maxBytes, int $ttl = self::SIGNED_URL_TTL): SignedUpload;

    /** A signed direct upload (PUT of the raw body). */
    public function signedPutUrl(string $key, string $contentType, int $ttl = self::SIGNED_URL_TTL): SignedUpload;

    /** @param 'inline'|'attachment' $disposition */
    public function signedDownloadUrl(string $key, string $disposition = 'attachment', int $ttl = self::SIGNED_URL_TTL): string;
}
