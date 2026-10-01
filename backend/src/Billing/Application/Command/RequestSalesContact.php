<?php

namespace App\Billing\Application\Command;

/** POST /contact (PRD §8.3): "Get in touch" about a plan that cannot be bought online. */
final class RequestSalesContact
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $userName,
        public readonly string $userEmail,
        /** "plan" */
        public readonly string $type,
        public readonly ?string $planId,
        public readonly string $email,
        public readonly string $phone,
    ) {
    }
}
