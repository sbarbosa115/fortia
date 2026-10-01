<?php

namespace App\Chat\Domain;

use App\Chat\Domain\Error\WriteQueueFull;

/**
 * The account changes the assistant proposed and that wait for the user's yes (PRD §7.19: "writes are queued
 * (maximum 50) and only run when the user says yes"). The client keeps the queue between turns, like the draft;
 * running an entry checks the caller's permissions again, so a tampered queue can do no more than the user's own API
 * calls.
 */
final class WriteQueue
{
    public const MAX = 50;

    /** @param list<array{id: string, tool: string, input: array<string, mixed>, label: string}> $items */
    private function __construct(private readonly array $items)
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public static function fromArray(mixed $raw): self
    {
        $items = [];
        foreach (\array_slice(\is_array($raw) ? array_values($raw) : [], 0, self::MAX) as $item) {
            if (!\is_array($item) || !\is_string($item['tool'] ?? null) || 1 !== preg_match('/^[a-z_]{1,64}$/', $item['tool'])) {
                continue;
            }
            $items[] = [
                'id' => \is_string($item['id'] ?? null) ? mb_substr($item['id'], 0, 40) : 'w'.(\count($items) + 1),
                'tool' => $item['tool'],
                'input' => \is_array($item['input'] ?? null) ? $item['input'] : [],
                'label' => \is_string($item['label'] ?? null) ? mb_substr($item['label'], 0, 300) : '',
            ];
        }

        return new self($items);
    }

    /** @param array<string, mixed> $input */
    public function with(string $tool, array $input, string $label): self
    {
        if (\count($this->items) >= self::MAX) {
            throw new WriteQueueFull(self::MAX);
        }
        $n = 1;
        foreach ($this->items as $item) {
            if (1 === preg_match('/^w(\d+)$/', $item['id'], $m)) {
                $n = max($n, (int) $m[1] + 1);
            }
        }

        return new self([...$this->items, ['id' => 'w'.$n, 'tool' => $tool, 'input' => $input, 'label' => mb_substr($label, 0, 300)]]);
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    /** @return list<array{id: string, tool: string, input: array<string, mixed>, label: string}> */
    public function items(): array
    {
        return $this->items;
    }
}
