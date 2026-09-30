<?php

declare(strict_types=1);

namespace App\Platform\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** A platform setting (PRD §6.23), e.g. "default_generation_model". */
#[ORM\Entity]
#[ORM\Table(name: 'app_setting')]
class AppSetting
{
    public const DEFAULT_GENERATION_MODEL = 'default_generation_model';

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'setting_key', length: 100)]
        private string $key,
        #[ORM\Column(type: Types::JSON)]
        private mixed $value,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function change(mixed $value): void
    {
        $this->value = $value;
    }
}
