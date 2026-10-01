<?php

namespace App\Reporting\UI\Http\Output;

use App\Shared\Domain\Document\QuestionnaireType;
use OpenApi\Attributes as OA;

/** The questionnaire the answers belong to: its title and the id of its public link (the flow slug when it has one). */
final class AnsweredQuestionnaireOutput
{
    public function __construct(
        public readonly string $questionnaire_id,
        public readonly string $title,
        #[OA\Property(enum: QuestionnaireType::VALUES)]
        public readonly string $type,
        public readonly bool $is_chain,
        public readonly string $public_id,
    ) {
    }
}
