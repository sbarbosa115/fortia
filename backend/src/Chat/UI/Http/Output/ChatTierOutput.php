<?php

namespace App\Chat\UI\Http\Output;

/** A diagnostic tier of the chat's draft; its score band is computed when it is saved. */
final class ChatTierOutput
{
    /**
     * @param list<string> $recommendations
     * @param list<string> $action_plan
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly array $recommendations,
        public readonly array $action_plan,
    ) {
    }
}
