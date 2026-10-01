<?php

namespace App\Integrations\Application\Port;

/**
 * The reads of the external API (PRD §8.11), paginated in the database (D17). They read the questionnaires and the
 * sessions of other contexts as plain rows; the answers themselves come from Responses' SessionQueries.
 */
interface ExternalCatalog
{
    /**
     * An account's root questionnaires, newest first: rows {id, flow_id, slug, title, description, is_active, type,
     * created_at, updated_at} and the total.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function questionnaires(string $customerId, int $page, int $pageSize): array;

    /**
     * The ids of a questionnaire's sessions, newest first, and the total.
     *
     * @return array{ids: list<string>, total: int}
     */
    public function sessionIds(string $questionnaireId, int $page, int $pageSize): array;
}
