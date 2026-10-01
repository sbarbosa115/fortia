<?php

namespace App\Reporting\Domain\Model;

use App\Shared\Domain\Error\Rejected;

/**
 * The paging rules of GET /questionnaire/{id}/answers (PRD §8.4): the status filter with its legacy aliases, a page
 * size of 20, 50 or 100, and an opaque base64 cursor (it carries the position of the next page).
 */
final class AnswersPage
{
    public const PAGE_SIZES = [20, 50, 100];
    public const DEFAULT_PAGE_SIZE = 100;

    /** The statuses each filter matches, legacy values included (PRD §6.9). null = every status. */
    private const STATUSES = [
        'completed' => ['completed'],
        'filling' => ['filling', 'in_progress'],
        'filled_out' => ['filled_out', 'submitted'],
        'processing' => ['processing'],
        'all' => null,
    ];
    private const ALIASES = ['in_progress' => 'filling', 'submitted' => 'filled_out'];

    /**
     * @param list<string>|null $statuses
     */
    private function __construct(
        public readonly ?array $statuses,
        public readonly int $limit,
        public readonly int $offset,
        public readonly ?string $assignationsId = null,
    ) {
    }

    /** $assignationsId narrows the page to one assignation's sessions (the Sheets export of PRD §10.11). */
    public static function of(?string $status, ?string $limit, ?string $cursor, ?string $assignationsId = null): self
    {
        $status = null === $status || '' === $status ? 'completed' : $status;
        $status = self::ALIASES[$status] ?? $status;
        if (!\array_key_exists($status, self::STATUSES)) {
            throw new Rejected('INVALID_REQUEST', 'The status must be completed, filling, filled_out, processing or all.', ['field' => 'status']);
        }
        $size = null === $limit || '' === $limit ? self::DEFAULT_PAGE_SIZE : (ctype_digit($limit) ? (int) $limit : 0);
        if (!\in_array($size, self::PAGE_SIZES, true)) {
            throw new Rejected('INVALID_PAGE_SIZE', 'The page size must be 20, 50 or 100.');
        }

        return new self(self::STATUSES[$status], $size, null === $cursor || '' === $cursor ? 0 : self::decode($cursor), '' === $assignationsId ? null : $assignationsId);
    }

    /** The cursor of the page after this one, or null when there is none. */
    public function nextCursor(int $total): ?string
    {
        $next = $this->offset + $this->limit;

        return $next < $total ? base64_encode((string) json_encode(['o' => $next])) : null;
    }

    private static function decode(string $cursor): int
    {
        $json = base64_decode($cursor, true);
        $data = false === $json ? null : json_decode($json, true);
        if (!\is_array($data) || !\is_int($data['o'] ?? null) || $data['o'] < 0) {
            throw new Rejected('INVALID_CURSOR', 'The cursor is not valid.');
        }

        return $data['o'];
    }
}
