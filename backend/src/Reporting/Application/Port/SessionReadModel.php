<?php

namespace App\Reporting\Application\Port;

/**
 * The reads Reporting needs over the stored sessions that the Responses context's queries do not offer: a page of
 * a questionnaire's sessions (paginated in the database) and the diagnostic results of all of them in one read.
 * Rows have the SessionQueries::sessionData() shape.
 */
interface SessionReadModel
{
    /**
     * Sessions of a questionnaire, newest first (ties by session id), from $offset, up to $limit; only one
     * assignation's when $assignationsId is given.
     *
     * @param list<string>|null $statuses null = every status
     *
     * @return list<array<string, mixed>>
     */
    public function page(string $questionnaireId, ?array $statuses, ?string $assignationsId, int $offset, int $limit): array;

    /** @param list<string>|null $statuses */
    public function count(string $questionnaireId, ?array $statuses, ?string $assignationsId): int;

    /**
     * Every session of a questionnaire, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    public function all(string $questionnaireId): array;

    /**
     * The diagnostic result of each of its sessions that has one.
     *
     * @return list<array<string, mixed>>
     */
    public function diagnostics(string $questionnaireId): array;
}
