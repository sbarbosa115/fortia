<?php

namespace App\Assignations\UI\Http\Input;

use App\Shared\UI\Http\Request\ProvidedFieldsTrait;
use App\Shared\UI\Http\Request\TracksProvidedFields;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * POST /projects (group "create") and PUT /projects/{id} (group "update", partial), PRD §8.9. No extra fields.
 * Create: organization_id, name and due_date are required. Update: name, due_date and assignation_ids cannot be null;
 * organization_id may be sent only unchanged (the handler checks it).
 */
final class ProjectInput implements TracksProvidedFields
{
    use ProvidedFieldsTrait;

    #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
    public ?string $organization_id = null;

    #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
    #[Assert\Length(max: 200)]
    public ?string $name = null;

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    #[Assert\Date(message: 'Enter a valid date (YYYY-MM-DD).')]
    public ?string $due_date = null;

    /** @var list<string>|null */
    #[Assert\All([new Assert\Type('string'), new Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])])]
    public ?array $assignation_ids = null;

    #[Assert\Callback(groups: ['create'])]
    public function validateCreate(ExecutionContextInterface $context): void
    {
        foreach (['organization_id', 'name', 'due_date'] as $field) {
            if (null === $this->{$field}) {
                $context->buildViolation('This value should not be null.')->atPath($field)->addViolation();
            }
        }
        if ($this->wasProvided('assignation_ids') && null === $this->assignation_ids) {
            $context->buildViolation('This value should not be null.')->atPath('assignation_ids')->addViolation();
        }
    }

    #[Assert\Callback(groups: ['update'])]
    public function validateUpdate(ExecutionContextInterface $context): void
    {
        if ([] === $this->providedFields()) {
            $context->buildViolation('Send at least one field to change.')->addViolation();
        }
        foreach (['organization_id', 'name', 'due_date', 'assignation_ids'] as $field) {
            if ($this->wasProvided($field) && null === $this->{$field}) {
                $context->buildViolation('This value should not be null.')->atPath($field)->addViolation();
            }
        }
    }

    /** @return list<string> */
    public function assignationIds(): array
    {
        return $this->assignation_ids ?? [];
    }

    /** @return array<string, mixed> the fields that were sent, for UpdateProject */
    public function fields(): array
    {
        $fields = [];
        foreach (['organization_id', 'name', 'description', 'due_date', 'assignation_ids'] as $field) {
            if ($this->wasProvided($field)) {
                $fields[$field] = $this->{$field};
            }
        }

        return $fields;
    }
}
