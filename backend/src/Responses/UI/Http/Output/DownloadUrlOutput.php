<?php

namespace App\Responses\UI\Http\Output;

/** A signed download of an answer's file (PRD §8.4 POST /answers-media/download-urls), valid 15 minutes. */
final class DownloadUrlOutput
{
    public function __construct(
        public readonly string $url,
        public readonly int $expires_in,
    ) {
    }
}
