<?php

namespace App\Chat\Application\Tool;

/** Small builders for the tools' JSON schemas. */
final class Schema
{
    /** @return array<string, mixed> */
    public static function string(string $description, ?int $maxLength = null): array
    {
        return ['type' => 'string', 'description' => $description] + (null === $maxLength ? [] : ['maxLength' => $maxLength]);
    }

    /** @return array<string, mixed> */
    public static function nullableString(string $description): array
    {
        return ['type' => ['string', 'null'], 'description' => $description];
    }

    /** @return array<string, mixed> */
    public static function id(string $what): array
    {
        return ['type' => 'string', 'description' => "The $what's id (a UUID)."];
    }

    /** @return array<string, mixed> */
    public static function bool(string $description): array
    {
        return ['type' => 'boolean', 'description' => $description];
    }

    /** @return array<string, mixed> */
    public static function int(string $description): array
    {
        return ['type' => 'integer', 'description' => $description];
    }

    /**
     * @param list<string> $values
     *
     * @return array<string, mixed>
     */
    public static function enum(array $values, string $description): array
    {
        return ['type' => 'string', 'enum' => $values, 'description' => $description];
    }

    /**
     * @param array<string, mixed> $items
     *
     * @return array<string, mixed>
     */
    public static function list(array $items, string $description): array
    {
        return ['type' => 'array', 'items' => $items, 'description' => $description];
    }

    /**
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    public static function object(array $properties, string $description = ''): array
    {
        return ['type' => 'object', 'properties' => $properties, 'description' => $description];
    }

    /**
     * The paging of a list the chat shows as a table of 5 or 10 rows (PRD §7.19).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function paging(): array
    {
        return [
            'page_size' => ['type' => 'integer', 'enum' => [5, 10], 'description' => 'Rows to show: 5 (default) or 10.'],
            'offset' => ['type' => 'integer', 'description' => 'Rows to skip ("see 5 more" = the previous offset + 5).'],
        ];
    }

    /**
     * A page of rows for the model, with what it needs to offer "see more".
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    public static function page(array $rows, int $offset, int $total): array
    {
        return ['rows' => $rows, 'offset' => $offset, 'shown' => \count($rows), 'total' => $total, 'has_more' => $offset + \count($rows) < $total];
    }
}
