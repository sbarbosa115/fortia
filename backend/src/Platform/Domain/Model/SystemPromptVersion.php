<?php

declare(strict_types=1);

namespace App\Platform\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One version of an editable system prompt (PRD §6.24, §7.20). The history of a key is its versions; the newest one
 * is the text in use. A key with no version uses the platform default (config/system_prompts/<key>.md).
 */
#[ORM\Entity]
#[ORM\Table(name: 'system_prompt_version')]
#[ORM\Index(name: 'idx_prompt_key_updated', columns: ['prompt_key', 'updated_at'])]
class SystemPromptVersion
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $versionId,
        #[ORM\Column(name: 'prompt_key', length: 100)]
        private string $key,
        #[ORM\Column(type: Types::TEXT)]
        private string $text,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
        #[ORM\Column(length: 180)]
        private string $updatedBy,
    ) {
    }

    public function versionId(): string
    {
        return $this->versionId;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updatedBy(): string
    {
        return $this->updatedBy;
    }
}
