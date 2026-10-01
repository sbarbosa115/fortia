<?php

namespace App\Chat\UI\Http\Output;

/** The ending of the chat's draft: the thank-you message and a diagnostic's tiers. */
final class ChatEndingOutput
{
    /** @param list<ChatTierOutput> $tiers */
    public function __construct(
        public readonly ?string $message,
        public readonly array $tiers,
    ) {
    }
}
