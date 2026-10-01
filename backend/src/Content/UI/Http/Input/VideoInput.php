<?php

namespace App\Content\UI\Http\Input;

use App\Content\Domain\VideoRules;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * POST /admin/videos and PUT /admin/videos/{id} (full replacement), PRD §8.13 with the fields of §6.22. title, url
 * and language are required; description and category default to "", order and duration_minutes to 0. No extra
 * fields.
 */
final class VideoInput
{
    #[Assert\NotNull]
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 200, normalizer: 'trim')]
    public ?string $title = null;

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    #[Assert\NotNull]
    #[Assert\Length(min: 1, max: 500)]
    public ?string $url = null;

    #[Assert\NotNull]
    #[Assert\Choice(choices: VideoRules::LANGUAGES, message: 'The language must be "es" or "en".')]
    public ?string $language = null;

    #[Assert\Length(max: 100)]
    public ?string $category = null;

    #[Assert\PositiveOrZero]
    public ?int $order = null;

    #[Assert\PositiveOrZero]
    public ?int $duration_minutes = null;

    #[Assert\Callback]
    public function validateUrl(ExecutionContextInterface $context): void
    {
        if (null !== $this->url && '' !== $this->url && mb_strlen($this->url) <= 500 && null === VideoRules::youtubeId($this->url)) {
            $context->buildViolation('The URL must be a YouTube video link.')->atPath('url')->addViolation();
        }
    }

    public function title(): string
    {
        return trim((string) $this->title);
    }

    public function description(): string
    {
        return trim($this->description ?? '');
    }

    public function url(): string
    {
        return trim((string) $this->url);
    }

    public function language(): string
    {
        return (string) $this->language;
    }

    public function category(): string
    {
        return trim($this->category ?? '');
    }

    public function order(): int
    {
        return $this->order ?? 0;
    }

    public function durationMinutes(): int
    {
        return $this->duration_minutes ?? 0;
    }
}
