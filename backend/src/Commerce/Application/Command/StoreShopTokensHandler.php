<?php

namespace App\Commerce\Application\Command;

use App\Commerce\Domain\Error\ShopifyNotConnected;
use App\Commerce\Domain\Repository\ShopifyConnectionRepository;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class StoreShopTokensHandler
{
    public function __construct(
        private readonly ShopifyConnectionRepository $connections,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(StoreShopTokens $command): void
    {
        $connection = $this->connections->find($command->customerId) ?? throw new ShopifyNotConnected();
        $tokens = $command->tokens;
        $connection->refresh($tokens->accessToken, $tokens->refreshToken, $tokens->expiresAt, $this->clock->now());
    }
}
