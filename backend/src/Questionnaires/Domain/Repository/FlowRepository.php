<?php

declare(strict_types=1);

namespace App\Questionnaires\Domain\Repository;

use App\Questionnaires\Domain\Model\Flow;

interface FlowRepository
{
    public function find(string $flowId): ?Flow;

    public function findBySlug(string $slug): ?Flow;

    public function findByQuestionnaire(string $questionnaireId): ?Flow;

    /** A flow by its id, its slug or its questionnaire's id (PRD §8.4 GET /flow/{identifier}). */
    public function findByIdentifier(string $identifier): ?Flow;

    public function slugExists(string $slug, ?string $exceptFlowId = null): bool;

    /**
     * The most recent flow whose store URL is one of these (PRD §8.4 GET /questionnaire/find).
     *
     * @param list<string> $sourceUrls
     */
    public function findLatestBySourceUrl(array $sourceUrls): ?Flow;

    public function add(Flow $flow): void;
}
