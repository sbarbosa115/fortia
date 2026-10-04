<?php

namespace App\Questionnaires\Application\Query;

use App\Questionnaires\Domain\Model\Diagnostic;
use App\Questionnaires\Domain\Model\Flow;
use App\Questionnaires\Domain\Model\Questionnaire;
use App\Questionnaires\Domain\Repository\DiagnosticRepository;
use App\Questionnaires\Domain\Repository\FlowRepository;
use App\Questionnaires\Domain\Repository\PromptRepository;
use App\Questionnaires\Domain\Repository\QuestionnaireRepository;
use App\Shared\Domain\Iso;

/**
 * Reads of the Questionnaires context for other contexts (they never name its entities): the questionnaire, its
 * flow, its diagnostic and its prompts, as views in the PRD's JSON shapes.
 */
final class QuestionnaireQueries
{
    public function __construct(
        private readonly QuestionnaireRepository $questionnaires,
        private readonly FlowRepository $flows,
        private readonly DiagnosticRepository $diagnostics,
        private readonly PromptRepository $prompts,
    ) {
    }

    public function find(string $questionnaireId): ?QuestionnaireView
    {
        $questionnaire = $this->questionnaires->find($questionnaireId);

        return null === $questionnaire ? null : new QuestionnaireView(self::questionnaireData($questionnaire));
    }

    public function flowOf(string $questionnaireId): ?FlowView
    {
        $flow = $this->flows->findByQuestionnaire($questionnaireId);

        return null === $flow ? null : new FlowView(self::flowData($flow));
    }

    /** By flow id, slug or questionnaire id. */
    public function findFlow(string $identifier): ?FlowView
    {
        $flow = $this->flows->findByIdentifier($identifier);

        return null === $flow ? null : new FlowView(self::flowData($flow));
    }

    /**
     * The scoring configuration: {tiers, recommendations, action_plan}, or null when it has none.
     *
     * @return array{tiers: list<array<string, mixed>>, recommendations: list<array<string, mixed>>, action_plan: list<array<string, mixed>>}|null
     */
    public function diagnosticOf(string $questionnaireId): ?array
    {
        $diagnostic = $this->diagnostics->findByQuestionnaire($questionnaireId);

        return null === $diagnostic ? null : self::diagnosticData($diagnostic);
    }

    /**
     * The prompts of a chain, in order: [{id, questionnaire_id, customer_id, s3_path, outcome, order}].
     *
     * @return list<array<string, mixed>>
     */
    public function promptsOf(string $questionnaireId): array
    {
        $out = [];
        foreach ($this->prompts->listByQuestionnaire($questionnaireId) as $prompt) {
            $out[] = [
                'id' => $prompt->id(),
                'questionnaire_id' => $prompt->questionnaireId(),
                'customer_id' => $prompt->customerId(),
                's3_path' => $prompt->s3Path(),
                'outcome' => $prompt->outcome(),
                'order' => $prompt->order(),
            ];
        }

        return $out;
    }

    /** @return list<QuestionnaireView> the generated stages of a chain, oldest first */
    public function stagesOf(string $rootQuestionnaireId): array
    {
        return array_map(
            static fn (Questionnaire $q): QuestionnaireView => new QuestionnaireView(self::questionnaireData($q)),
            $this->questionnaires->childrenOf($rootQuestionnaireId),
        );
    }

    public function stageGeneratedBy(string $sessionId): ?QuestionnaireView
    {
        $questionnaire = $this->questionnaires->findByOriginSession($sessionId);

        return null === $questionnaire ? null : new QuestionnaireView(self::questionnaireData($questionnaire));
    }

    public function countRootsOf(string $customerId): int
    {
        return $this->questionnaires->countRootsOf($customerId);
    }

    /** @return array<string, mixed> */
    public static function questionnaireData(Questionnaire $q): array
    {
        return [
            'questionnaire_id' => $q->questionnaireId(),
            'customer_id' => $q->customerId(),
            'title' => $q->title(),
            'description' => $q->description(),
            'disclaimer' => $q->disclaimer(),
            'capture_user_data' => $q->captureUserData(),
            'landing_page' => $q->landingPage(),
            'type' => $q->type(),
            'is_active' => $q->isActive(),
            'on_completed' => $q->onCompleted(),
            'parent' => $q->parent(),
            'origin_session_id' => $q->originSessionId(),
            'session_id' => null,
            'started_at' => null,
            'ended_at' => null,
            'question_count' => $q->questionCount(),
            'is_chain' => $q->isChain(),
            'slug' => $q->slug(),
            'tags' => $q->tags(),
            'questions' => $q->questions(),
            'created_at' => Iso::datetime($q->createdAt()),
            'updated_at' => Iso::datetime($q->updatedAt()),
        ];
    }

    /** @return array<string, mixed> */
    public static function flowData(Flow $flow): array
    {
        return [
            'id' => $flow->id(),
            'slug' => $flow->slug(),
            'detail' => $flow->detail(),
            'states' => $flow->states(),
            'customer_id' => $flow->customerId(),
            'questionnaire_id' => $flow->questionnaireId(),
            'source_url' => $flow->sourceUrl(),
            'cta' => $flow->cta(),
            'layout' => $flow->layout(),
            'result_copy' => $flow->resultCopy(),
            'created_at' => Iso::datetime($flow->createdAt()),
            'updated_at' => Iso::datetime($flow->updatedAt()),
        ];
    }

    /** @return array{tiers: list<array<string, mixed>>, recommendations: list<array<string, mixed>>, action_plan: list<array<string, mixed>>} */
    public static function diagnosticData(Diagnostic $diagnostic): array
    {
        return [
            'tiers' => $diagnostic->tiers(),
            'recommendations' => $diagnostic->recommendations(),
            'action_plan' => $diagnostic->actionPlan(),
        ];
    }
}
