<?php

namespace App\Integrations\Application\Command;

use App\Integrations\Domain\Error\ApiKeyNotFound;
use App\Integrations\Domain\Model\ApiKey;
use App\Integrations\Domain\Repository\ApiKeyRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RevokeApiKeyHandler
{
    public function __construct(private readonly ApiKeyRepository $keys)
    {
    }

    public function __invoke(RevokeApiKey $command): void
    {
        $key = $this->keys->find($command->apiKeyId);
        // Another account's key, or one already revoked, is not found (another tenant's id is 404, never 403).
        if (null === $key || ApiKey::ACTIVE !== $key->status() || !$command->caller->owns($key->customerId())) {
            throw new ApiKeyNotFound();
        }
        $key->revoke();
    }
}
