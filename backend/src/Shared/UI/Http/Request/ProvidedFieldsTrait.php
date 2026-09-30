<?php

namespace App\Shared\UI\Http\Request;

trait ProvidedFieldsTrait
{
    /** @var list<string> */
    private array $providedKeys = [];

    /** @param list<string> $keys */
    public function markProvided(array $keys): void
    {
        $this->providedKeys = $keys;
    }

    public function wasProvided(string $key): bool
    {
        return \in_array($key, $this->providedKeys, true);
    }

    /** @return list<string> */
    public function providedFields(): array
    {
        return $this->providedKeys;
    }
}
