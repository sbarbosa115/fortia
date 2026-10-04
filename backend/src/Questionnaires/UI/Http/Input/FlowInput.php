<?php

namespace App\Questionnaires\UI\Http\Input;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST /questionnaire (PRD §8.4): a flow. The single `questionnaire` state carries the whole questionnaire in
 * parameters.questionnaire; a `prompt` state its text's storage key in parameters.key (or the text in
 * parameters.text); a diagnostic's scoring travels in the questionnaire's on_completed. See Domain\Flow\FlowDraft.
 */
class FlowInput
{
    #[OA\Property(description: 'Empty: generated from the title. ^[a-z0-9]+(-[a-z0-9]+)*$, unique across the system', nullable: true)]
    public ?string $slug = null;

    /** @var list<array<string, mixed>>|null */
    #[Assert\NotNull]
    #[OA\Property(type: 'array', items: new OA\Items(type: 'object', additionalProperties: true))]
    public ?array $states = null;

    /** @var array<string, mixed>|null */
    #[OA\Property(type: 'object', nullable: true, additionalProperties: true)]
    public ?array $cta = null;

    /** @var list<string>|null */
    #[OA\Property(type: 'array', nullable: true, items: new OA\Items(type: 'string', enum: ['score', 'tier', 'categories', 'recommendations', 'action_plan', 'pdf', 'cta']))]
    public ?array $layout = null;

    /** @var array<string, mixed>|null */
    #[OA\Property(type: 'object', nullable: true, additionalProperties: new OA\AdditionalProperties(type: 'string'))]
    public ?array $result_copy = null;

    #[OA\Property(nullable: true)]
    public ?string $detail = null;

    /** @var list<string>|null */
    #[OA\Property(description: 'Free-text labels ("AP-03"): trimmed, repeats (any case) dropped, at most 20 of at most 40 characters. Omitted on PUT: the questionnaire keeps its tags', items: new OA\Items(type: 'string', maxLength: 40), maxItems: 20)]
    public ?array $tags = null;
}
