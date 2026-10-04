<?php

namespace App\Identity\Application\Command;

use App\Identity\Domain\Model\SystemSettings;
use App\Identity\Domain\Repository\CustomerRepository;
use App\Identity\Domain\Repository\SystemSettingsRepository;
use App\Shared\Application\Security\SecretBox;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ChangeSystemSettingsHandler
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly SystemSettingsRepository $settings,
        private readonly SecretBox $box,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ChangeSystemSettings $command): void
    {
        $this->customers->get($command->customerId);
        $now = $this->clock->now();
        $settings = $this->settings->find($command->customerId);
        if (null === $settings) {
            $settings = new SystemSettings($command->customerId, $now);
            $this->settings->add($settings);
        }

        if (SmtpFields::touches($command->fields)) {
            $settings->changeSmtp(SmtpFields::merge($settings->smtp(), $command->fields, $this->box), $now);
        }
        if (\array_key_exists('openai_api_key', $command->fields)) {
            $key = trim((string) $command->fields['openai_api_key']);
            if ('' === $key) {
                $settings->changeOpenAiKey(null, null, $now);
            } else {
                $settings->changeOpenAiKey($this->box->seal($key), $key, $now);
            }
        }
    }
}
