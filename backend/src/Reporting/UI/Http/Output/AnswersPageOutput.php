<?php

namespace App\Reporting\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * GET /questionnaire/{id}/answers (PRD §8.4): {items, next_cursor}, plus the total of the filter, the questionnaire's
 * header data and its generated chain stages, so the answers screen needs no other request (§10.8).
 */
final class AnswersPageOutput
{
    /**
     * @param list<AnswerOutput>         $items
     * @param list<GeneratedStageOutput> $generated_stages
     */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: AnswerOutput::class)))]
        public readonly array $items,
        #[OA\Property(description: 'Opaque; pass it back as ?cursor= for the next page. null on the last page.')]
        public readonly ?string $next_cursor,
        public readonly int $total,
        public readonly AnsweredQuestionnaireOutput $questionnaire,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: GeneratedStageOutput::class)))]
        public readonly array $generated_stages,
    ) {
    }

    /** @param array{items: list<array{session: array<string, mixed>, member: array{organization_user_id: string, name: string, email: string|null, phone: string|null}|null, chain: array{stage: int, total_stages: int}|null}>, next_cursor: string|null, total: int, questionnaire: array{questionnaire_id: string, title: string, type: string, is_chain: bool, public_id: string}, generated_stages: list<array<string, mixed>>} $page */
    public static function of(array $page): self
    {
        $q = $page['questionnaire'];

        return new self(
            array_map(static fn (array $item): AnswerOutput => AnswerOutput::of($item['session'], $item['member'], $item['chain']), $page['items']),
            $page['next_cursor'],
            $page['total'],
            new AnsweredQuestionnaireOutput($q['questionnaire_id'], $q['title'], $q['type'], $q['is_chain'], $q['public_id']),
            array_map(static fn (array $s): GeneratedStageOutput => new GeneratedStageOutput(
                (string) $s['questionnaire_id'],
                (string) $s['title'],
                isset($s['origin_session_id']) ? (string) $s['origin_session_id'] : null,
                isset($s['created_at']) ? (string) $s['created_at'] : null,
            ), $page['generated_stages']),
        );
    }
}
