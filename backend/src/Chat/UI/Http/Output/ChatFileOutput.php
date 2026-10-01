<?php

namespace App\Chat\UI\Http\Output;

use OpenApi\Attributes as OA;

/** POST /chat/files: the attached file's name and the text read from it, sent back with its message on every turn. */
final class ChatFileOutput
{
    public function __construct(
        #[OA\Property(maxLength: 255)]
        public readonly string $filename,
        #[OA\Property(maxLength: 100000, minLength: 1)]
        public readonly string $text,
    ) {
    }
}
