<?php

namespace App\Branding\UI\Http\Input;

use App\Branding\Domain\StylesShape;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** PRD §8.5 POST /styles: website? (empty = none) and styles? (partial, camelCase, validated against §6.18). */
final class StylesInput
{
    #[Assert\Length(max: 2048)]
    #[Assert\Url(protocols: ['http', 'https'], requireTld: true, message: 'Enter a website address that starts with http:// or https://.')]
    #[OA\Property(nullable: true, description: 'The website to read the brand from; empty or null = none')]
    public ?string $website = null;

    /** @var array<string, mixed>|null */
    #[OA\Property(type: 'object', nullable: true, additionalProperties: true, description: 'Partial styles (PRD §6.18), deep-merged over the stored ones when the website did not change')]
    public ?array $styles = null;

    #[Assert\Callback]
    public function validateStyles(ExecutionContextInterface $context): void
    {
        foreach (StylesShape::violations($this->styles ?? []) as $violation) {
            $context->buildViolation($violation['message'])->atPath($violation['field'])->addViolation();
        }
    }

    public function website(): ?string
    {
        $website = trim((string) $this->website);

        return '' === $website ? null : $website;
    }
}
