<?php

namespace App\Identity\UI\Http\Input;

use App\Shared\UI\Http\Request\ProvidedFieldsTrait;
use App\Shared\UI\Http\Request\TracksProvidedFields;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * PATCH /customer/{customer_id}/system-settings: at least one field, no extra fields. openai_api_key: empty or null
 * removes the account's key (the platform key is used again). analytics_base_url: the account's usage/analytics
 * service, an http(s) URL (empty or null: the platform's ANALYTICS_BASE_URL is used); analytics_api_key: its key
 * (empty or null removes it).
 */
final class SystemSettingsInput implements TracksProvidedFields
{
    use ProvidedFieldsTrait;
    use SmtpServerFields;

    #[Assert\Length(max: 512)]
    public ?string $openai_api_key = null;

    #[Assert\Length(max: 255)]
    #[Assert\Url(protocols: ['http', 'https'], requireTld: false)]
    public ?string $analytics_base_url = null;

    #[Assert\Length(max: 512)]
    public ?string $analytics_api_key = null;

    #[Assert\Callback]
    public function validateProvided(ExecutionContextInterface $context): void
    {
        if ([] === $this->providedFields()) {
            $context->buildViolation('Send at least one setting to change.')->addViolation();
        }
    }
}
