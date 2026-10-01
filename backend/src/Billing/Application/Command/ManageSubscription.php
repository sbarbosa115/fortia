<?php

namespace App\Billing\Application\Command;

/**
 * The actions on an account's subscription that need no input (PRD §7.4, §8.3): revert a scheduled downgrade,
 * cancel at period end, resume.
 */
final class ManageSubscription
{
    public const REVERT = 'revert';
    public const CANCEL = 'cancel';
    public const RESUME = 'resume';

    public function __construct(
        public readonly string $customerId,
        /** @var self::REVERT|self::CANCEL|self::RESUME */
        public readonly string $action,
    ) {
    }
}
