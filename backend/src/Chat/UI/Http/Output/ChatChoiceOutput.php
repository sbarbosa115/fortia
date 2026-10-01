<?php

namespace App\Chat\UI\Http\Output;

/** A choice of a draft question; value = its score in a diagnostic, else null. */
final class ChatChoiceOutput
{
    public function __construct(
        public readonly string $label,
        public readonly int|float|null $value,
    ) {
    }
}
