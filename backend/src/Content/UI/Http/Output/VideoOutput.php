<?php

namespace App\Content\UI\Http\Output;

/** A documentation video (PRD §6.22). */
final class VideoOutput
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $description,
        public readonly string $url,
        public readonly string $language,
        public readonly string $category,
        public readonly int $order,
        public readonly int $duration_minutes,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
    ) {
    }

    /** @param array<string, mixed> $data VideoQueries::videoData() */
    public static function of(array $data): self
    {
        return new self(
            (string) $data['id'],
            (string) $data['title'],
            (string) $data['description'],
            (string) $data['url'],
            (string) $data['language'],
            (string) $data['category'],
            (int) $data['order'],
            (int) $data['duration_minutes'],
            null === $data['created_at'] ? null : (string) $data['created_at'],
            null === $data['updated_at'] ? null : (string) $data['updated_at'],
        );
    }
}
