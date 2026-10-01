<?php

namespace App\Chat\UI\Http\Output;

/** Why a confirmed change failed: the code and text its HTTP endpoint would answer. */
final class ChatErrorOutput
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
    ) {
    }
}
