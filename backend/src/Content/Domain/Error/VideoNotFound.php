<?php

namespace App\Content\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/** 404: no documentation video with that id (PRD §8.13 videos). */
final class VideoNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('VIDEO_NOT_FOUND', 'Video not found.');
    }
}
