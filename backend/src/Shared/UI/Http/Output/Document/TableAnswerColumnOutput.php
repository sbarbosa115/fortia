<?php

namespace App\Shared\UI\Http\Output\Document;

/** A column of a table answer: the key its cells are stored under, and its header. */
final class TableAnswerColumnOutput
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
    ) {
    }
}
