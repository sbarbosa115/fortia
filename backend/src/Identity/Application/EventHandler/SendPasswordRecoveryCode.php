<?php

namespace App\Identity\Application\EventHandler;

use App\Identity\Application\PasswordRecovery;
use App\Identity\Application\Port\AccountEmails;
use App\Identity\Domain\Event\PasswordRecoveryRequested;
use App\Identity\Domain\Model\PasswordResetCode;
use App\Identity\Domain\Repository\CustomerRepository;
use App\Identity\Domain\Repository\PasswordResetCodeRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Creates the recovery code (stored hashed, valid for an hour) and emails it in the account's language (PRD §7.21).
 * The newest code replaces any earlier one.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class SendPasswordRecoveryCode
{
    public function __construct(
        private readonly PasswordResetCodeRepository $codes,
        private readonly CustomerRepository $customers,
        private readonly AccountEmails $emails,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(PasswordRecoveryRequested $event): void
    {
        $now = $this->clock->now();
        $code = PasswordRecovery::newCode();
        $this->codes->add(new PasswordResetCode(
            Ids::uuid4(),
            $event->email(),
            PasswordRecovery::hash($code),
            $now->modify(\sprintf('+%d minutes', PasswordRecovery::VALID_MINUTES)),
            $now,
        ));
        $language = null === $event->customerId() ? null : $this->customers->find($event->customerId())?->language();
        $this->emails->recoveryCode($event->email(), $code, PasswordRecovery::VALID_MINUTES, $language ?? 'es-CO');
    }
}
