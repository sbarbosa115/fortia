<?php

namespace App\Questionnaires\Application\Query;

use App\Assignations\Application\Query\AssignationQueries;
use App\Questionnaires\Domain\Flow\StoreUrl;
use App\Questionnaires\Domain\Repository\DiagnosticRepository;
use App\Questionnaires\Domain\Repository\FlowRepository;
use App\Questionnaires\Domain\Repository\PromptRepository;
use App\Questionnaires\Domain\Repository\QuestionnaireRepository;
use App\Shared\Application\Storage\ObjectNotFound;
use App\Shared\Application\Storage\ObjectStorage;

/**
 * The reads behind the authoring endpoints (PRD §8.4): a questionnaire as the console reads it (the diagnostic merged
 * into on_completed), its prompts with their texts, and the public flow lookups.
 */
final class QuestionnaireDetails
{
    public function __construct(
        private readonly QuestionnaireRepository $questionnaires,
        private readonly FlowRepository $flows,
        private readonly DiagnosticRepository $diagnostics,
        private readonly PromptRepository $prompts,
        private readonly AssignationQueries $assignations,
        private readonly ObjectStorage $storage,
    ) {
    }

    /**
     * The full questionnaire (PRD §8.4 GET /questionnaire/{id}): "with the diagnostic tiers merged into
     * on_completed".
     *
     * @return array<string, mixed>|null
     */
    public function find(string $questionnaireId): ?array
    {
        $questionnaire = $this->questionnaires->find($questionnaireId);
        if (null === $questionnaire) {
            return null;
        }
        $data = QuestionnaireQueries::questionnaireData($questionnaire);
        $diagnostic = $this->diagnostics->findByQuestionnaire($questionnaireId);
        if (null !== $diagnostic) {
            $data['on_completed'] = array_merge(
                \is_array($data['on_completed']) ? $data['on_completed'] : ['type' => 'diagnostic'],
                QuestionnaireQueries::diagnosticData($diagnostic),
            );
        }

        return $data;
    }

    /**
     * The prompts of a chain in order, each with its text loaded from object storage (PRD §8.4
     * GET /questionnaire/{id}/prompts). A text that is gone reads as "".
     *
     * @return list<array{id: string, questionnaire_id: string, customer_id: string, s3_path: string, outcome: string|null, order: int, text: string}>
     */
    public function prompts(string $questionnaireId): array
    {
        $out = [];
        foreach ($this->prompts->listByQuestionnaire($questionnaireId) as $prompt) {
            try {
                $text = $this->storage->get($prompt->s3Path());
            } catch (ObjectNotFound) {
                $text = '';
            }
            $out[] = [
                'id' => $prompt->id(),
                'questionnaire_id' => $prompt->questionnaireId(),
                'customer_id' => $prompt->customerId(),
                's3_path' => $prompt->s3Path(),
                'outcome' => $prompt->outcome(),
                'order' => $prompt->order(),
                'text' => $text,
            ];
        }

        return $out;
    }

    /**
     * GET /flow/{identifier}: by flow id, slug or questionnaire id. A questionnaire assigned to an organization is
     * only answered through /a/{id}, so its flow is not found by slug or flow id (PRD §8.4).
     */
    public function publicFlow(string $identifier): ?FlowView
    {
        $flow = $this->flows->findByIdentifier($identifier);
        if (null === $flow) {
            return null;
        }
        if ($flow->questionnaireId() !== $identifier && $this->assignations->isQuestionnaireAssigned($flow->questionnaireId())) {
            return null;
        }

        return new FlowView(QuestionnaireQueries::flowData($flow));
    }

    /**
     * GET /questionnaire/find: the most recent flow whose store URL matches the page (normalized), falling back to
     * the site's origin. Returns the flow id.
     */
    public function flowIdForStorePage(string $url): ?string
    {
        $flow = $this->flows->findLatestBySourceUrl(StoreUrl::candidates($url))
            ?? $this->flows->findLatestBySourceUrl(StoreUrl::originCandidates($url));

        return $flow?->id();
    }
}
