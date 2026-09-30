<?php

namespace App\Organizations\UI\Http\Input;

use App\Organizations\Domain\MemberRules;
use App\Organizations\Domain\Model\MemberDraft;
use App\Organizations\Domain\OrganizationRules;
use App\Shared\UI\Http\Request\ProvidedFieldsTrait;
use App\Shared\UI\Http\Request\TracksProvidedFields;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * POST /organizations (group "create") and PUT /organizations/{id} (group "update", partial), PRD §8.7. No extra
 * fields, in the organization or in its members. The domain's own rules (normalized name 1–120, domain shape,
 * member rules) run here too, so they answer before the plan gate.
 */
final class OrganizationInput implements TracksProvidedFields
{
    use ProvidedFieldsTrait;

    public ?string $name = null;

    public ?string $domain_email = null;

    public ?string $description = null;

    public ?bool $active = null;

    /** @var list<array<string, mixed>>|null */
    #[Assert\All([
        new Assert\Collection(
            fields: [
                'organization_user_id' => new Assert\Optional([new Assert\Type('string'), new Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]),
                'name' => new Assert\Required([new Assert\NotNull(), new Assert\Type('string'), new Assert\Length(max: 200)]),
                'email' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 255)]),
                'phone' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 50)]),
                'role' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 120)]),
                'area' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 120)]),
            ],
            allowExtraFields: false,
        ),
    ])]
    public ?array $organization_users = null;

    #[Assert\Callback(groups: ['create'])]
    public function validateCreate(ExecutionContextInterface $context): void
    {
        if (null === $this->name) {
            $context->buildViolation('Name is required.')->atPath('name')->addViolation();
        }
        foreach (['active', 'organization_users'] as $field) {
            if ($this->wasProvided($field) && null === $this->{$field}) {
                $context->buildViolation('This value should not be null.')->atPath($field)->addViolation();
            }
        }
        $this->validateRules($context);
    }

    #[Assert\Callback(groups: ['update'])]
    public function validateUpdate(ExecutionContextInterface $context): void
    {
        if ([] === $this->providedFields()) {
            $context->buildViolation('Send at least one field to change.')->addViolation();
        }
        foreach (['name', 'active', 'organization_users'] as $field) {
            if ($this->wasProvided($field) && null === $this->{$field}) {
                $context->buildViolation('This value should not be null.')->atPath($field)->addViolation();
            }
        }
        $this->validateRules($context);
    }

    /** @return array<string, mixed> the fields that were sent, for SaveOrganization */
    public function fields(): array
    {
        $fields = [];
        foreach (['name', 'domain_email', 'description', 'active', 'organization_users'] as $field) {
            if ($this->wasProvided($field)) {
                $fields[$field] = $this->{$field};
            }
        }

        return $fields;
    }

    private function validateRules(ExecutionContextInterface $context): void
    {
        $violations = OrganizationRules::violations(
            null === $this->name ? null : OrganizationRules::normalizeName($this->name),
            OrganizationRules::normalizeDomain($this->domain_email),
            OrganizationRules::normalizeDescription($this->description),
        );
        $members = $this->organization_users ?? [];
        // Shape errors (a missing or non-text name) are the Collection constraint's; the rules need the shape right.
        if ([] === array_filter($members, static fn (array $m): bool => !\is_string($m['name'] ?? null))) {
            $violations = [...$violations, ...MemberRules::violations(array_map(MemberDraft::of(...), $members))];
        }
        foreach ($violations as $violation) {
            $context->buildViolation($violation['message'])->atPath($violation['field'])->addViolation();
        }
    }
}
