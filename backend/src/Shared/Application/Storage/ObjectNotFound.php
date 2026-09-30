<?php

declare(strict_types=1);

namespace App\Shared\Application\Storage;

final class ObjectNotFound extends \RuntimeException
{
    public static function key(string $key): self
    {
        return new self(\sprintf('No object with key "%s".', $key));
    }
}
