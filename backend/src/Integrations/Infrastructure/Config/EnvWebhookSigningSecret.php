<?php

namespace App\Integrations\Infrastructure\Config;

use App\Integrations\Application\Port\WebhookSigningSecret;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class EnvWebhookSigningSecret implements WebhookSigningSecret
{
    public function __construct(
        #[Autowire(env: 'WEBHOOK_SIGNING_SECRET')]
        private readonly string $secret,
    ) {
    }

    public function value(): string
    {
        return $this->secret;
    }
}
