<?php

namespace App\Integrations\Application\Command;

use App\Integrations\Domain\ApiKeyFormat;
use App\Integrations\Domain\Model\ApiKey;
use App\Integrations\Domain\Repository\ApiKeyRepository;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateApiKeyHandler
{
    public function __construct(
        private readonly ApiKeyRepository $keys,
        private readonly Clock $clock,
    ) {
    }

    /** @return string the plaintext key ("QAIRE-" + 64 hex); only its SHA-256 is stored (PRD §6.20) */
    public function __invoke(CreateApiKey $command): string
    {
        $plaintext = ApiKeyFormat::generate();
        $now = $this->clock->now();
        $expiresAt = null === $command->expirationDays ? null : $now->modify('+'.$command->expirationDays.' days');
        $this->keys->add(new ApiKey(ApiKey::hash($plaintext), $command->caller->customerId, trim($command->name), $now, $expiresAt));

        return $plaintext;
    }
}
