<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Application\TokenPair;
use App\Identity\Domain\Model\User;

/** Issues the console's tokens (PRD §13.1): an id token of 24 h and a refresh token of 30 days. */
interface TokenIssuer
{
    public function issue(User $user): TokenPair;
}
