<?php

namespace App\Shared\Application\Storage;

/**
 * Checks the signed URLs the local storage hands out (the storage controller serves them). With a cloud object
 * store, the provider does this and the controller is not used.
 */
interface SignedRequestVerifier
{
    /**
     * The upload a signed form allows, or null when the signature is wrong or expired.
     *
     * @param array<string, mixed> $fields the form fields sent with the file
     *
     * @return array{key: string, content_type: string, min: int, max: int}|null
     */
    public function verifyUploadForm(array $fields): ?array;

    /**
     * @param array<string, mixed> $query
     *
     * @return array{key: string, content_type: string}|null
     */
    public function verifyPut(array $query): ?array;

    /**
     * @param array<string, mixed> $query
     *
     * @return array{key: string, disposition: string}|null
     */
    public function verifyDownload(array $query): ?array;

    /** Stores a verified upload. */
    public function store(string $key, string $contents, string $contentType): void;

    /** Reads an object for a verified download. */
    public function read(string $key): string;
}
