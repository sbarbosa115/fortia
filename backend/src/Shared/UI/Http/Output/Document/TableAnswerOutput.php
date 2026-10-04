<?php

namespace App\Shared\UI\Http\Output\Document;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** A table question's answer, structured to be shown as a table: its columns in order and the respondent's rows. */
final class TableAnswerOutput
{
    /**
     * @param list<TableAnswerColumnOutput> $columns
     * @param list<TableAnswerRowOutput>    $rows
     */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: TableAnswerColumnOutput::class)))]
        public readonly array $columns,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: TableAnswerRowOutput::class)))]
        public readonly array $rows,
    ) {
    }

    /** @param array{columns: list<array{key: string, label: string}>, rows: list<array{label: ?string, cells: array<string, string>}>} $t */
    public static function fromArray(array $t): self
    {
        return new self(
            array_map(static fn (array $c): TableAnswerColumnOutput => new TableAnswerColumnOutput($c['key'], $c['label']), $t['columns']),
            array_map(static fn (array $r): TableAnswerRowOutput => new TableAnswerRowOutput($r['label'], $r['cells']), $t['rows']),
        );
    }
}
