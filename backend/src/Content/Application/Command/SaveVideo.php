<?php

namespace App\Content\Application\Command;

/**
 * Creates (videoId null) or replaces (PUT, full replacement) a documentation video, PRD §8.13 and §6.22. The Input
 * has validated the fields; returns the video's id. 404 VIDEO_NOT_FOUND when replacing an unknown id.
 */
final class SaveVideo
{
    public function __construct(
        public readonly ?string $videoId,
        public readonly string $title,
        public readonly string $description,
        public readonly string $url,
        public readonly string $language,
        public readonly string $category,
        public readonly int $order,
        public readonly int $durationMinutes,
    ) {
    }
}
