<?php

namespace App\Questionnaires\Application\Query;

use App\Shared\Domain\Error\Rejected;
use App\Shared\Domain\Text;

/**
 * The filters of GET /questionnaire (PRD §8.4): type, sort_by, order, is_active, parent, page, page_size, search, and
 * tag (one of the questionnaire's tags, matched whole without case or accents).
 * A bad type, sort, order or is_active is refused with its own code; page and page_size are clamped.
 */
final class ListingCriteria
{
    public const TYPES = ['default', 'quiz_funnel', 'diagnostic', 'process_mapping'];
    public const SORTS = ['created_at', 'updated_at'];
    public const DEFAULT_PAGE_SIZE = 20;
    public const MAX_PAGE_SIZE = 100;

    /** @param list<string> $searchWords */
    public function __construct(
        /** null = every account (a platform Admin). */
        public readonly ?string $customerId,
        public readonly string $parent = 'ROOT',
        public readonly ?string $type = null,
        public readonly ?bool $isActive = null,
        public readonly array $searchWords = [],
        public readonly string $sortBy = 'created_at',
        public readonly string $order = 'desc',
        public readonly int $page = 1,
        public readonly int $pageSize = self::DEFAULT_PAGE_SIZE,
        public readonly ?string $tag = null,
    ) {
    }

    /**
     * @param array<string, mixed> $query the query string
     *
     * @throws Rejected INVALID_TYPE, INVALID_SORT, INVALID_ORDER, INVALID_IS_ACTIVE
     */
    public static function fromQuery(array $query, ?string $customerId): self
    {
        $type = self::text($query, 'type');
        if (null !== $type && !\in_array($type, self::TYPES, true)) {
            throw new Rejected('INVALID_TYPE', 'type must be one of: '.implode(', ', self::TYPES).'.');
        }
        $sortBy = self::text($query, 'sort_by') ?? 'created_at';
        if (!\in_array($sortBy, self::SORTS, true)) {
            throw new Rejected('INVALID_SORT', 'sort_by must be created_at or updated_at.');
        }
        $order = self::text($query, 'order') ?? 'desc';
        if (!\in_array($order, ['asc', 'desc'], true)) {
            throw new Rejected('INVALID_ORDER', 'order must be asc or desc.');
        }
        $isActive = match (self::text($query, 'is_active')) {
            null => null,
            'true', '1' => true,
            'false', '0' => false,
            default => throw new Rejected('INVALID_IS_ACTIVE', 'is_active must be true, false, 1 or 0.'),
        };

        return new self(
            $customerId,
            self::text($query, 'parent') ?? 'ROOT',
            $type,
            $isActive,
            Text::searchWords(self::text($query, 'search') ?? ''),
            $sortBy,
            $order,
            max(1, self::int($query, 'page') ?? 1),
            max(1, min(self::MAX_PAGE_SIZE, self::int($query, 'page_size') ?? self::DEFAULT_PAGE_SIZE)),
            self::text($query, 'tag'),
        );
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->pageSize;
    }

    /** @param array<string, mixed> $query */
    private static function text(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /** @param array<string, mixed> $query */
    private static function int(array $query, string $key): ?int
    {
        $value = self::text($query, $key);

        return null !== $value && 1 === preg_match('/^-?\d{1,9}$/', $value) ? (int) $value : null;
    }
}
