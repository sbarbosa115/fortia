<?php

namespace App\Content\Application\Query;

use App\Content\Domain\Error\InvalidLanguage;
use App\Content\Domain\Model\Video;
use App\Content\Domain\Repository\VideoRepository;
use App\Content\Domain\VideoRules;
use App\Shared\Domain\Iso;

/**
 * Reads of the documentation videos in the PRD §6.22 shape ({id, title, description, url, language, category, order,
 * duration_minutes, created_at, updated_at}), for GET /videos, the admin API and the assistant's list_videos tool.
 */
final class VideoQueries
{
    public function __construct(private readonly VideoRepository $videos)
    {
    }

    /**
     * Sorted by order and then by title (PRD §8.12).
     *
     * @param string|null $language null = both languages; anything but es|en is 400 INVALID_LANGUAGE
     *
     * @return list<array<string, mixed>>
     */
    public function list(?string $language): array
    {
        if (null !== $language && !VideoRules::isLanguage($language)) {
            throw new InvalidLanguage();
        }

        return array_map(self::videoData(...), $this->videos->listFor($language));
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        $video = $this->videos->find($id);

        return null === $video ? null : self::videoData($video);
    }

    /** @return array<string, mixed> */
    public static function videoData(Video $video): array
    {
        return [
            'id' => $video->id(),
            'title' => $video->title(),
            'description' => $video->description(),
            'url' => $video->url(),
            'language' => $video->language(),
            'category' => $video->category(),
            'order' => $video->order(),
            'duration_minutes' => $video->durationMinutes(),
            'created_at' => Iso::datetime($video->createdAt()),
            'updated_at' => Iso::datetime($video->updatedAt()),
        ];
    }
}
