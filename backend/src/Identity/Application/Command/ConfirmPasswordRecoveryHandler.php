<?php

namespace App\Identity\Application\Command;

use App\Identity\Application\PasswordRecovery;
use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\PasswordResetCodeRepository;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ConfirmPasswordRecoveryHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordResetCodeRepository $codes,
        private readonly PasswordHasher $hasher,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ConfirmPasswordRecovery $command): string
    {
        $email = EmailAddress::normalize($command->email);
        $user = $this->users->findByEmail($email);
        $code = null === $user ? null : $this->codes->latestFor($email);
        if (null === $user || null === $code || $code->isUsed()) {
            return PasswordRecovery::INVALID_RESET_CODE;
        }
        if ($code->attempts() >= PasswordRecovery::MAX_ATTEMPTS) {
            return PasswordRecovery::TOO_MANY_ATTEMPTS;
        }
        if (!PasswordRecovery::matches($code->codeHash(), $command->code)) {
            $code->recordAttempt();

            return PasswordRecovery::INVALID_RESET_CODE;
        }
        $now = $this->clock->now();
        if ($code->isExpiredAt($now)) {
            return PasswordRecovery::EXPIRED_RESET_CODE;
        }
        if (mb_strlen($command->password) < PasswordRecovery::MIN_PASSWORD_LENGTH) {
            return PasswordRecovery::INVALID_PASSWORD;
        }
        $user->setPasswordHash($this->hasher->hash($command->password), $now);
        $code->markUsed($now);

        return PasswordRecovery::OK;
    }
}
