<?php

namespace App\Content\Domain\Repository;

use App\Content\Domain\Model\Video;

interface VideoRepository
{
    public function find(string $id): ?Video;

    /**
     * @param string|null $language null = both languages
     *
     * @return list<Video> by order, then title (PRD §8.12)
     */
    public function listFor(?string $language): array;

    public function add(Video $video): void;

    public function remove(Video $video): void;
}
