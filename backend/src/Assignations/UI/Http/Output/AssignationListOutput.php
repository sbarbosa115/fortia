<?php

namespace App\Assignations\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** GET /assignations (PRD §8.8): one page of enriched assignations, newest first, with numbered pagination. */
final class AssignationListOutput
{
    /** @param list<AssignationOutput> $assignations */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: AssignationOutput::class)))]
        public readonly array $assignations,
        public readonly ProjectPaginationOutput $pagination,
    ) {
    }
}
