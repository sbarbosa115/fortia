<?php

namespace App\Chat\UI\Http\Output;

use OpenApi\Attributes as OA;

/**
 * A file question's template in the chat's draft: a stored one (key, filename) or one the chat wrote (filename,
 * columns, example_rows), saved as a CSV.
 */
final class ChatTemplateOutput
{
    /**
     * @param list<string>|null       $columns
     * @param list<list<string>>|null $example_rows
     */
    public function __construct(
        public readonly string $filename,
        public readonly ?string $key,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string'), nullable: true)]
        public readonly ?array $columns,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'array', items: new OA\Items(type: 'string')), nullable: true)]
        public readonly ?array $example_rows,
    ) {
    }
}
