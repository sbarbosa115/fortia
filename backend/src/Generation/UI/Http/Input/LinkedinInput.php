<?php

namespace App\Generation\UI\Http\Input;

use App\Generation\Domain\LinkedinRequest;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** PRD §8.4 POST /questionnaire/linkedin. */
final class LinkedinInput
{
    /** A public profile: https?://([a-z]{2,3}.)?(www.)?linkedin.com/in/... */
    #[Assert\NotNull]
    #[Assert\Length(max: 2048)]
    public ?string $linkedin_url = null;

    /** en or es; any other value becomes es. */
    #[Assert\Length(max: 16)]
    public ?string $language = null;

    #[Assert\Callback]
    public function validateUrl(ExecutionContextInterface $context): void
    {
        if (null !== $this->linkedin_url && !LinkedinRequest::isProfileUrl($this->linkedin_url)) {
            $context->buildViolation('This must be the URL of a LinkedIn profile (https://www.linkedin.com/in/…).')->atPath('linkedin_url')->addViolation();
        }
    }
}
