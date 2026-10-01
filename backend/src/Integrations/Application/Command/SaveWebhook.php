<?php

namespace App\Integrations\Application\Command;

use App\Shared\Application\Security\Caller;

/**
 * POST /webhooks (create) and PUT /webhooks/{id} (partial update), PRD §8.11. Fields: url (https only), event_type
 * (questionnaire.completed), method (POST); on update only the ones sent change. Returns the webhook id.
 */
final class SaveWebhook
{
    /**
     * @param array{url?: string, event_type?: string, method?: string} $fields
     */
    private function __construct(
        public readonly Caller $caller,
        public readonly ?string $webhookId,
        public readonly array $fields,
    ) {
    }

    /** @param array{url?: string, event_type?: string, method?: string} $fields */
    public static function create(Caller $caller, array $fields): self
    {
        return new self($caller, null, $fields);
    }

    /** @param array{url?: string, event_type?: string, method?: string} $fields */
    public static function update(Caller $caller, string $webhookId, array $fields): self
    {
        return new self($caller, $webhookId, $fields);
    }
}
