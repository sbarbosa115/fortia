<?php

namespace App\Identity\Application\Command;

use App\Identity\Domain\Repository\CustomerRepository;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DescribeWorkspaceHandler
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly Clock $clock,
    ) {
    }

    /** @return array{name: string|null, language: string, website: string|null} */
    public function __invoke(DescribeWorkspace $command): array
    {
        $customer = $this->customers->get($command->customerId);
        $now = $this->clock->now();
        $fields = $command->fields;
        $name = \array_key_exists('name', $fields) ? self::blankToNull($fields['name']) : $customer->workspaceName();
        $website = \array_key_exists('website', $fields) ? self::blankToNull($fields['website']) : $customer->website();
        $customer->describeWorkspace($name, $website, $now);
        if (isset($fields['language'])) {
            $customer->changeSettings(['language' => $fields['language']], $now);
        }

        return ['name' => $customer->workspaceName(), 'language' => $customer->language(), 'website' => $customer->website()];
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }
}
