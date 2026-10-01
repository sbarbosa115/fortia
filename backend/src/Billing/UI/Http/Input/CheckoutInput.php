<?php

namespace App\Billing\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** POST /checkout/session and /checkout/plan-change (PRD §8.3). */
final class CheckoutInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $plan_id = null;

    #[Assert\NotNull]
    #[Assert\Choice(choices: ['month', 'year'])]
    public ?string $billing_interval = 'month';
}
