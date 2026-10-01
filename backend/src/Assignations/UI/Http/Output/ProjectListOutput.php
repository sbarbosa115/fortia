<?php

namespace App\Assignations\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** GET /projects (PRD §8.9): {projects, pagination}. */
final class ProjectListOutput
{
    /** @param list<ProjectOutput> $projects */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ProjectOutput::class)))]
        public readonly array $projects,
        public readonly ProjectPaginationOutput $pagination,
    ) {
    }
}
