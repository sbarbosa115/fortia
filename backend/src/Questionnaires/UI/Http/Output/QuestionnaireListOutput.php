<?php

namespace App\Questionnaires\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** GET /questionnaire (PRD §8.1 pagination style 2): {items, page, page_size, total, total_pages}. */
final class QuestionnaireListOutput
{
    /** @param list<QuestionnaireListItemOutput> $items */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: QuestionnaireListItemOutput::class)))]
        public readonly array $items,
        public readonly int $page,
        public readonly int $page_size,
        public readonly int $total,
        public readonly int $total_pages,
    ) {
    }

    /** @param list<array<string, mixed>> $rows */
    public static function of(array $rows, int $page, int $pageSize, int $total): self
    {
        return new self(
            array_map(static fn (array $row): QuestionnaireListItemOutput => QuestionnaireListItemOutput::fromArray($row), $rows),
            $page,
            $pageSize,
            $total,
            (int) ceil($total / max(1, $pageSize)),
        );
    }
}
