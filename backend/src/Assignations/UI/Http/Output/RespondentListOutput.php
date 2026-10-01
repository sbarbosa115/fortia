<?php

namespace App\Assignations\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** GET /assignations/{id}/respondents (PRD §8.8): a page of the audience; next_cursor is null on the last one. */
final class RespondentListOutput
{
    /** @param list<RespondentOutput> $respondents */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: RespondentOutput::class)))]
        public readonly array $respondents,
        public readonly ?string $next_cursor,
    ) {
    }
}
