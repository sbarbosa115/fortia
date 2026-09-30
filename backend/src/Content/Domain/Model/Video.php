<?php

namespace App\Content\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** A documentation video (PRD §6.22): a YouTube URL, in es or en. */
#[ORM\Entity]
#[ORM\Table(name: 'video')]
#[ORM\Index(name: 'idx_video_language', columns: ['language', 'video_order'])]
class Video
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $id,
        #[ORM\Column(length: 200)]
        private string $title,
        #[ORM\Column(type: Types::TEXT)]
        private string $description,
        #[ORM\Column(length: 500)]
        private string $url,
        #[ORM\Column(length: 2)]
        private string $language,
        #[ORM\Column(length: 100)]
        private string $category,
        #[ORM\Column(name: 'video_order')]
        private int $order,
        #[ORM\Column]
        private int $durationMinutes,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
    }

    public function change(string $title, string $description, string $url, string $language, string $category, int $order, int $durationMinutes, \DateTimeImmutable $at): void
    {
        $this->title = $title;
        $this->description = $description;
        $this->url = $url;
        $this->language = $language;
        $this->category = $category;
        $this->order = $order;
        $this->durationMinutes = $durationMinutes;
        $this->updatedAt = $at;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function language(): string
    {
        return $this->language;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function order(): int
    {
        return $this->order;
    }

    public function durationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
