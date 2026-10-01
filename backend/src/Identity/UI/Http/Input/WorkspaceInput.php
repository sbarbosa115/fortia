<?php

namespace App\Identity\UI\Http\Input;

use App\Identity\Domain\Model\Customer;
use App\Shared\UI\Http\Request\ProvidedFieldsTrait;
use App\Shared\UI\Http\Request\TracksProvidedFields;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** D15 PATCH /customer/workspace: onboarding step 2's name, language and website. At least one field. */
final class WorkspaceInput implements TracksProvidedFields
{
    use ProvidedFieldsTrait;

    #[Assert\Length(max: 120)]
    public ?string $name = null;

    #[Assert\Choice(choices: Customer::LANGUAGES)]
    public ?string $language = null;

    #[Assert\Length(max: 2048)]
    #[Assert\Url(requireTld: true)]
    public ?string $website = null;

    #[Assert\Callback]
    public function validateProvided(ExecutionContextInterface $context): void
    {
        if ([] === $this->providedFields()) {
            $context->buildViolation('Send at least one field to change.')->addViolation();
        }
        if ($this->wasProvided('language') && null === $this->language) {
            $context->buildViolation('The language cannot be null.')->atPath('language')->addViolation();
        }
    }

    /** @return array{name?: string|null, language?: string, website?: string|null} */
    public function provided(): array
    {
        $values = [];
        if ($this->wasProvided('name')) {
            $values['name'] = $this->name;
        }
        if ($this->wasProvided('language') && null !== $this->language) {
            $values['language'] = $this->language;
        }
        if ($this->wasProvided('website')) {
            $values['website'] = $this->website;
        }

        return $values;
    }
}
