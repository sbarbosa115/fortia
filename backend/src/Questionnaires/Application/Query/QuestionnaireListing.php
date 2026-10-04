<?php

namespace App\Questionnaires\Application\Query;

/**
 * The questionnaire listing (PRD §8.4 GET /questionnaire), paginated and searched in the database (D16, D17).
 */
interface QuestionnaireListing
{
    /**
     * One page of rows in the listing shape (questionnaire_id, customer_id, parent, origin_session_id, title,
     * description, created_at, updated_at, is_active, on_completed, status, landing_page, capture_user_data,
     * question_count, is_chain, slug, type), and the total of rows that match.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function page(ListingCriteria $criteria): array;

    /**
     * The listing row of one questionnaire.
     *
     * @return array<string, mixed>|null
     */
    public function row(string $questionnaireId): ?array;

    /**
     * Every tag the account's questionnaires carry (every account's for null), each once whatever its case
     * (the oldest questionnaire's spelling), sorted.
     *
     * @return list<string>
     */
    public function tags(?string $customerId): array;
}
