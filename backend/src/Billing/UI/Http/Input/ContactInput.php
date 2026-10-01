<?php

namespace App\Billing\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** POST /contact (PRD §8.3): type "plan" needs a plan_id; a valid email and a phone of 1–50 characters. */
final class ContactInput
{
    /** The one email rule of the whole app (D13): the same as EMAIL_PATTERN in assets/shared/lib/text.ts. */
    public const EMAIL_PATTERN = '/^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/';

    #[Assert\NotNull]
    #[Assert\Choice(choices: ['plan'])]
    public ?string $type = null;

    #[Assert\Length(max: 100)]
    public ?string $plan_id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 254)]
    #[Assert\Regex(pattern: self::EMAIL_PATTERN, message: 'Enter a valid email address.')]
    public ?string $email = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 50)]
    public ?string $phone = null;

    #[Assert\Callback]
    public function validatePlan(ExecutionContextInterface $context): void
    {
        if ('plan' === $this->type && (null === $this->plan_id || '' === trim($this->plan_id))) {
            $context->buildViolation('A plan is required to contact sales about a plan.')->atPath('plan_id')->addViolation();
        }
    }
}
