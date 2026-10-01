<?php

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Model\PasswordResetCode;

interface PasswordResetCodeRepository
{
    /** The newest code sent to an email (only the last one is valid). */
    public function latestFor(string $email): ?PasswordResetCode;

    public function add(PasswordResetCode $code): void;
}
