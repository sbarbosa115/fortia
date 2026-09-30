<?php

namespace App\Shared\UI\Http\Output\Document;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * The canonical results of a session (PRD §8.4 GET /questionnaire/session/{id}/results), which the respondent app
 * shows at /session/{id}/results. $extra carries a configured client-specific result (samurai8, livingood; D8).
 */
final class SessionResultsOutput
{
    /**
     * @param list<string>|null          $layout
     * @param array<string, string>|null $result_copy
     * @param list<ProductOutput>|null   $products
     * @param array<string, mixed>|null  $ai_team_profile
     * @param array<string, mixed>|null  $extra
     */
    public function __construct(
        public readonly string $session_id,
        public readonly string $customer_id,
        public readonly string $questionnaire_id,
        public readonly ?CtaOutput $cta,
        #[OA\Property(type: 'array', nullable: true, items: new OA\Items(type: 'string', enum: FlowOutput::LAYOUT_BLOCKS))]
        public readonly ?array $layout,
        #[OA\Property(type: 'object', nullable: true, additionalProperties: new OA\AdditionalProperties(type: 'string'))]
        public readonly ?array $result_copy,
        #[OA\Property(type: 'array', nullable: true, items: new OA\Items(ref: new Model(type: ProductOutput::class)))]
        public readonly ?array $products,
        #[OA\Property(type: 'object', nullable: true, additionalProperties: true)]
        public readonly ?array $ai_team_profile,
        public readonly ?DiagnosticResultOutput $diagnostic,
        #[OA\Property(type: 'object', nullable: true, additionalProperties: true)]
        public readonly ?array $extra = null,
    ) {
    }
}
