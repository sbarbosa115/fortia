<?php

namespace App\Identity\UI\Http\Input;

use App\Identity\Domain\Model\Customer;
use App\Shared\UI\Http\Request\ProvidedFieldsTrait;
use App\Shared\UI\Http\Request\TracksProvidedFields;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * PRD §8.3 PATCH /customer/{customer_id}/settings: at least one field, no extra fields, language never null,
 * tracking ids ≤ 64 (empty or null clears them), max_files a strict integer 1–20 (null resets it).
 */
final class SettingsInput implements TracksProvidedFields
{
    use ProvidedFieldsTrait;

    #[Assert\Choice(choices: Customer::LANGUAGES)]
    public ?string $language = null;

    #[Assert\Length(max: 2048)]
    #[Assert\Url(requireTld: true)]
    public ?string $transcription_url = null;

    #[Assert\Length(max: 64)]
    public ?string $pixel_id = null;

    #[Assert\Length(max: 64)]
    public ?string $linkedin_partner_id = null;

    #[Assert\Length(max: 64)]
    public ?string $linkedin_conversion_id = null;

    #[Assert\Length(max: 64)]
    public ?string $google_ads_id = null;

    #[Assert\Length(max: 64)]
    public ?string $google_ads_conversion_label = null;

    #[Assert\Range(min: 1, max: 20)]
    public ?int $max_files = null;

    #[Assert\Callback]
    public function validateProvided(ExecutionContextInterface $context): void
    {
        if ([] === $this->providedFields()) {
            $context->buildViolation('Send at least one setting to change.')->addViolation();
        }
        if ($this->wasProvided('language') && null === $this->language) {
            $context->buildViolation('The language cannot be null.')->atPath('language')->addViolation();
        }
    }

    /** @return array<string, mixed> the fields sent, by name */
    public function provided(): array
    {
        $values = [];
        foreach ($this->providedFields() as $field) {
            $values[$field] = $this->{$field};
        }

        return $values;
    }
}
