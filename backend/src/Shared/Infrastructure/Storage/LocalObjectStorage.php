<?php

namespace App\Shared\Infrastructure\Storage;

use App\Shared\Application\Storage\ObjectNotFound;
use App\Shared\Application\Storage\ObjectStorage;
use App\Shared\Application\Storage\SignedRequestVerifier;
use App\Shared\Application\Storage\SignedUpload;
use App\Shared\Domain\Clock;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

/**
 * Object storage on the local disk (STORAGE_DIR), with HMAC-signed upload and download URLs served by
 * Shared\UI\Http\Web\StorageController. It gives the apps the same contract as a cloud store: a signed form
 * upload ({url, fields, key, expires_in}), a signed PUT and a signed GET, each valid 15 minutes.
 */
final class LocalObjectStorage implements ObjectStorage, SignedRequestVerifier
{
    public function __construct(
        private readonly FilesystemOperator $storage,
        private readonly string $signingSecret,
        private readonly string $appUrl,
        private readonly Clock $clock,
    ) {
    }

    public function put(string $key, string $contents, string $contentType = 'application/octet-stream'): void
    {
        $this->storage->write($key, $contents);
    }

    public function get(string $key): string
    {
        try {
            return $this->storage->read($key);
        } catch (FilesystemException $e) {
            throw ObjectNotFound::key($key);
        }
    }

    public function exists(string $key): bool
    {
        try {
            return $this->storage->fileExists($key);
        } catch (FilesystemException) {
            return false;
        }
    }

    public function delete(string $key): void
    {
        try {
            $this->storage->delete($key);
        } catch (FilesystemException) {
        }
    }

    public function signedUploadForm(string $key, string $contentType, int $minBytes, int $maxBytes, int $ttl = self::SIGNED_URL_TTL): SignedUpload
    {
        $fields = [
            'key' => $key,
            'content_type' => $contentType,
            'min' => (string) $minBytes,
            'max' => (string) $maxBytes,
            'expires' => (string) ($this->clock->now()->getTimestamp() + $ttl),
        ];
        $fields['signature'] = $this->sign('upload', $fields);

        return new SignedUpload(rtrim($this->appUrl, '/').'/storage/upload', $key, $ttl, $fields);
    }

    public function signedPutUrl(string $key, string $contentType, int $ttl = self::SIGNED_URL_TTL): SignedUpload
    {
        $query = ['key' => $key, 'content_type' => $contentType, 'expires' => (string) ($this->clock->now()->getTimestamp() + $ttl)];
        $query['signature'] = $this->sign('put', $query);

        return new SignedUpload(rtrim($this->appUrl, '/').'/storage/put?'.http_build_query($query), $key, $ttl);
    }

    public function signedDownloadUrl(string $key, string $disposition = 'attachment', int $ttl = self::SIGNED_URL_TTL): string
    {
        $query = ['key' => $key, 'disposition' => $disposition, 'expires' => (string) ($this->clock->now()->getTimestamp() + $ttl)];
        $query['signature'] = $this->sign('download', $query);

        return rtrim($this->appUrl, '/').'/storage/download?'.http_build_query($query);
    }

    public function verifyUploadForm(array $fields): ?array
    {
        $signed = $this->pick($fields, ['key', 'content_type', 'min', 'max', 'expires']);
        if (null === $signed || !$this->valid('upload', $signed, $fields['signature'] ?? null)) {
            return null;
        }

        return ['key' => $signed['key'], 'content_type' => $signed['content_type'], 'min' => (int) $signed['min'], 'max' => (int) $signed['max']];
    }

    public function verifyPut(array $query): ?array
    {
        $signed = $this->pick($query, ['key', 'content_type', 'expires']);
        if (null === $signed || !$this->valid('put', $signed, $query['signature'] ?? null)) {
            return null;
        }

        return ['key' => $signed['key'], 'content_type' => $signed['content_type']];
    }

    public function verifyDownload(array $query): ?array
    {
        $signed = $this->pick($query, ['key', 'disposition', 'expires']);
        if (null === $signed || !$this->valid('download', $signed, $query['signature'] ?? null)) {
            return null;
        }

        return ['key' => $signed['key'], 'disposition' => $signed['disposition']];
    }

    public function store(string $key, string $contents, string $contentType): void
    {
        $this->put($key, $contents, $contentType);
    }

    public function read(string $key): string
    {
        return $this->get($key);
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string>         $names
     *
     * @return array<string, string>|null
     */
    private function pick(array $values, array $names): ?array
    {
        $picked = [];
        foreach ($names as $name) {
            if (!isset($values[$name]) || !\is_string($values[$name])) {
                return null;
            }
            $picked[$name] = $values[$name];
        }

        return $picked;
    }

    /** @param array<string, string> $signed */
    private function valid(string $purpose, array $signed, mixed $signature): bool
    {
        if (!\is_string($signature) || (int) $signed['expires'] < $this->clock->now()->getTimestamp()) {
            return false;
        }
        if (str_contains($signed['key'], '..')) {
            return false;
        }

        return hash_equals($this->sign($purpose, $signed), $signature);
    }

    /** @param array<string, string> $values */
    private function sign(string $purpose, array $values): string
    {
        ksort($values);

        return hash_hmac('sha256', $purpose.'|'.http_build_query($values), $this->signingSecret);
    }
}
