<?php

namespace App\Questionnaires\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** GET /questionnaire/{id}/prompts: {prompts: [...]} in order. */
final class PromptListOutput
{
    /** @param list<PromptOutput> $prompts */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: PromptOutput::class)))]
        public readonly array $prompts,
    ) {
    }
}
