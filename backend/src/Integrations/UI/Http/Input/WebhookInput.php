<?php

namespace App\Integrations\UI\Http\Input;

use App\Shared\UI\Http\Request\ProvidedFieldsTrait;
use App\Shared\UI\Http\Request\TracksProvidedFields;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * POST /webhooks (group "create": url required) and PUT /webhooks/{id} (group "update": partial, at least one
 * field), PRD §8.11: url https only, event_type questionnaire.completed, method POST (both default to those).
 */
final class WebhookInput implements TracksProvidedFields
{
    use ProvidedFieldsTrait;

    #[Assert\Length(max: 2048)]
    #[Assert\Regex(pattern: '#^https://#i', message: 'The URL must start with https://.')]
    #[Assert\Url(protocols: ['https'], requireTld: true)]
    public ?string $url = null;

    #[Assert\Choice(choices: ['questionnaire.completed'])]
    public ?string $event_type = null;

    #[Assert\Choice(choices: ['POST'])]
    public ?string $method = null;

    #[Assert\Callback(groups: ['create'])]
    public function validateCreate(ExecutionContextInterface $context): void
    {
        if (null === $this->url || '' === trim($this->url)) {
            $context->buildViolation('URL is required.')->atPath('url')->addViolation();
        }
    }

    #[Assert\Callback(groups: ['update'])]
    public function validateUpdate(ExecutionContextInterface $context): void
    {
        if ([] === $this->providedFields()) {
            $context->buildViolation('Send at least one field to change.')->addViolation();
        }
        foreach (['url', 'event_type', 'method'] as $field) {
            if ($this->wasProvided($field) && null === $this->{$field}) {
                $context->buildViolation('This value should not be null.')->atPath($field)->addViolation();
            }
        }
    }

    /** @return array{url?: string, event_type?: string, method?: string} the fields that were sent */
    public function fields(): array
    {
        $fields = [];
        if (null !== $this->url) {
            $fields['url'] = $this->url;
        }
        if (null !== $this->event_type) {
            $fields['event_type'] = $this->event_type;
        }
        if (null !== $this->method) {
            $fields['method'] = $this->method;
        }

        return $fields;
    }
}
