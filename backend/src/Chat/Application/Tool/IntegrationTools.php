<?php

namespace App\Chat\Application\Tool;

use App\Billing\Application\Features;
use App\Billing\Application\PlanGate;
use App\Content\Application\Query\VideoQueries;
use App\Integrations\Application\Command\DeleteWebhook;
use App\Integrations\Application\Command\RevokeApiKey;
use App\Integrations\Application\Command\SaveWebhook;
use App\Integrations\Application\Query\IntegrationQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotFound;

/**
 * PRD §7.19 "Integrations" (list_api_keys, revoke_api_key, webhook CRUD) and "Documentation" (list_videos), with
 * the checks of PRD §8.11/§8.12: revoking and webhook writes need write permission and the caller's own key or
 * webhook (another account's is 404), creating a webhook Feat(webhook). Creating API keys is deliberately not
 * offered (the plaintext key must never pass through the chat).
 */
final class IntegrationTools implements ChatToolbox
{
    public function __construct(
        private readonly IntegrationQueries $integrations,
        private readonly VideoQueries $videos,
        private readonly PlanGate $gate,
        private readonly CommandBus $commands,
    ) {
    }

    public function tools(): array
    {
        $webhook = [
            'url' => Schema::string('Where the event is sent: an https:// URL.', 2048),
            'event_type' => Schema::enum(['questionnaire.completed'], 'The event (only questionnaire.completed).'),
        ];

        return [
            ChatTool::read(
                'list_api_keys',
                'The account\'s active API keys (never their secret).',
                [],
                [],
                fn (Caller $caller): array => ['rows' => $this->integrations->apiKeys($caller->customerId)],
            ),
            ChatTool::write(
                'revoke_api_key',
                'Revokes an API key: it stops working at once.',
                ['api_key_id' => Schema::string('The key\'s id, from list_api_keys.', 64)],
                ['api_key_id'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::write($caller);

                    return $this->ownedKey($caller, $input->string('api_key_id', 64))['name'];
                },
                function (Caller $caller, ToolInput $input): array {
                    $this->commands->dispatch(new RevokeApiKey($caller, $input->string('api_key_id', 64)));

                    return ['revoked' => true];
                },
            ),
            ChatTool::read(
                'list_webhooks',
                'The account\'s webhooks.',
                [],
                [],
                fn (Caller $caller): array => ['rows' => array_map(static fn (array $w): array => array_diff_key($w, ['customer_id' => true]), $this->integrations->webhooks($caller->customerId))],
            ),
            ChatTool::write(
                'create_webhook',
                'Adds a webhook that receives each completed questionnaire.',
                $webhook,
                ['url'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::write($caller);
                    $this->gate->feature($caller, Features::WEBHOOK);

                    return self::url($input);
                },
                function (Caller $caller, ToolInput $input): array {
                    $id = (string) $this->commands->dispatch(SaveWebhook::create($caller, ['url' => self::url($input), 'event_type' => 'questionnaire.completed', 'method' => 'POST']));

                    return ['webhook_id' => $id];
                },
            ),
            ChatTool::write(
                'update_webhook',
                'Changes a webhook\'s URL.',
                ['webhook_id' => Schema::id('webhook')] + $webhook,
                ['webhook_id', 'url'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::write($caller);
                    $this->ownedWebhook($caller, $input->uuid('webhook_id'));

                    return self::url($input);
                },
                function (Caller $caller, ToolInput $input): array {
                    $id = $input->uuid('webhook_id');
                    $this->commands->dispatch(SaveWebhook::update($caller, $id, ['url' => self::url($input)]));

                    return ['webhook_id' => $id];
                },
            ),
            ChatTool::write(
                'delete_webhook',
                'Deletes a webhook and its delivery log.',
                ['webhook_id' => Schema::id('webhook')],
                ['webhook_id'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::write($caller);

                    return (string) $this->ownedWebhook($caller, $input->uuid('webhook_id'))['url'];
                },
                function (Caller $caller, ToolInput $input): array {
                    $id = $input->uuid('webhook_id');
                    $this->commands->dispatch(new DeleteWebhook($caller, $id));

                    return ['webhook_id' => $id, 'deleted' => true];
                },
            ),
            ChatTool::read(
                'list_videos',
                'The documentation videos, in order.',
                ['language' => Schema::enum(['es', 'en'], 'Only the videos in this language.')],
                [],
                fn (Caller $caller, ToolInput $input): array => ['rows' => array_map(
                    static fn (array $v): array => array_intersect_key($v, array_flip(['id', 'title', 'description', 'url', 'language', 'order'])),
                    $this->videos->list(null === ($input->all()['language'] ?? null) ? null : $input->choice('language', ['es', 'en'])),
                )],
            ),
        ];
    }

    /** @return array{id: string, name: string} */
    private function ownedKey(Caller $caller, string $id): array
    {
        foreach ($this->integrations->apiKeys($caller->customerId) as $key) {
            if ($key['id'] === $id) {
                return $key;
            }
        }

        throw new NotFound('API_KEY_NOT_FOUND', 'API key not found.');
    }

    /** @return array<string, mixed> */
    private function ownedWebhook(Caller $caller, string $id): array
    {
        $webhook = $this->integrations->webhook($id);
        if (null === $webhook || !$caller->owns((string) $webhook['customer_id'])) {
            throw new NotFound('WEBHOOK_NOT_FOUND', 'Webhook not found.');
        }

        return $webhook;
    }

    private static function url(ToolInput $input): string
    {
        $url = $input->string('url', 2048);
        $parts = parse_url($url);
        if (false === filter_var($url, \FILTER_VALIDATE_URL) || !\is_array($parts) || 'https' !== strtolower((string) ($parts['scheme'] ?? '')) || !str_contains((string) ($parts['host'] ?? ''), '.')) {
            throw ToolInput::invalid('url', 'The URL must start with https://.');
        }

        return $url;
    }
}
