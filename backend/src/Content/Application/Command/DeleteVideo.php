<?php

namespace App\Content\Application\Command;

/** Deletes a documentation video (PRD §8.13). 404 VIDEO_NOT_FOUND for an unknown id. */
final class DeleteVideo
{
    public function __construct(public readonly string $videoId)
    {
    }
}
