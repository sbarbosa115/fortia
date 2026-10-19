<?php

namespace App\Assignations\Application\Query;

use App\Assignations\Domain\Error\InvalidProjectStatus;
use App\Assignations\Domain\ProjectStatus;
use App\Shared\Domain\Error\Rejected;
use App\Shared\Domain\Ids;
use App\Shared\Domain\Text;

/**
 * The query of GET /projects (PRD §8.9): page, page_size (default 10, clamped to 1–100), status (review, progress —
 * which includes pending —, correction, overdue, approved; anything else is 400 INVALID_PROJECT_STATUS) and q
 * (every word in the project's name or its organization's name); organization_id lists one organization's projects
 * (400 INVALID_UUID when it is not a UUID).
 */
final class ProjectListCriteria
{
    public const DEFAULT_PAGE_SIZE = 10;
    public const MAX_PAGE_SIZE = 100;

    /** @param list<string> $searchWords */
    public function __construct(
        /** null = every account (a platform Admin). */
        public readonly ?string $customerId,
        public readonly ?string $status = null,
        public readonly array $searchWords = [],
        public readonly int $page = 1,
        public readonly int $pageSize = self::DEFAULT_PAGE_SIZE,
        public readonly ?string $organizationId = null,
    ) {
    }

    /**
     * @param array<string, mixed> $query the query string
     *
     * @throws InvalidProjectStatus
     * @throws Rejected             INVALID_UUID
     */
    public static function fromQuery(array $query, ?string $customerId): self
    {
        $status = self::text($query, 'status');
        if (null !== $status && !\in_array($status, ProjectStatus::FILTERS, true)) {
            throw new InvalidProjectStatus();
        }
        $organizationId = self::text($query, 'organization_id');
        if (null !== $organizationId && !Ids::isUuid4($organizationId)) {
            throw new Rejected('INVALID_UUID', 'organization_id is not a valid UUID.');
        }

        return new self(
            $customerId,
            $status,
            Text::searchWords(self::text($query, 'q') ?? ''),
            max(1, self::int($query, 'page') ?? 1),
            max(1, min(self::MAX_PAGE_SIZE, self::int($query, 'page_size') ?? self::DEFAULT_PAGE_SIZE)),
            null === $organizationId ? null : strtolower($organizationId),
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
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        return trim($value);
    }

    /** @param array<string, mixed> $query */
    private static function int(array $query, string $key): ?int
    {
        $value = $query[$key] ?? null;

        return \is_string($value) && 1 === preg_match('/^\d{1,9}$/', $value) ? (int) $value : null;
    }
}
