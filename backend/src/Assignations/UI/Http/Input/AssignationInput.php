<?php

namespace App\Assignations\UI\Http\Input;

use App\Shared\UI\Http\Request\ProvidedFieldsTrait;
use App\Shared\UI\Http\Request\TracksProvidedFields;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * POST /assignations (group "create") and PUT /assignations/{id} (group "update", partial), PRD §8.8. No extra
 * fields. Create: organization_id, questionnaire_id, name, max_follow_ups, type and questions are required; active
 * defaults to true and audience to {type: all}. Update: `type` cannot be sent; `due_date: null` clears it.
 */
final class AssignationInput implements TracksProvidedFields
{
    use ProvidedFieldsTrait;

    private const FIELDS = ['organization_id', 'questionnaire_id', 'name', 'description', 'max_follow_ups', 'active', 'type', 'due_date', 'audience', 'questions'];

    #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
    public ?string $organization_id = null;

    #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
    public ?string $questionnaire_id = null;

    #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
    #[Assert\Length(max: 200)]
    public ?string $name = null;

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    #[Assert\PositiveOrZero]
    public ?int $max_follow_ups = null;

    public ?bool $active = null;

    #[Assert\Choice(choices: ['default', 'follow_up'])]
    public ?string $type = null;

    #[Assert\Date(message: 'Enter a valid date (YYYY-MM-DD).')]
    public ?string $due_date = null;

    /** @var array<string, mixed>|null {type: all | members | area | role, values: string[]} */
    #[OA\Property(type: 'object', properties: [
        new OA\Property(property: 'type', type: 'string', enum: ['all', 'members', 'area', 'role']),
        new OA\Property(property: 'values', type: 'array', items: new OA\Items(type: 'string')),
    ])]
    #[Assert\Collection(fields: [
        'type' => [new Assert\NotNull(), new Assert\Choice(choices: ['all', 'members', 'area', 'role'])],
        'values' => new Assert\Optional([new Assert\Type('array'), new Assert\Count(max: 500), new Assert\All([new Assert\Type('string'), new Assert\Length(max: 200)])]),
    ])]
    public ?array $audience = null;

    /** @var list<array<string, mixed>>|null the registration slide (respondent login): at least one question */
    #[OA\Property(type: 'array', items: new OA\Items(type: 'object'))]
    #[Assert\Count(min: 1)]
    #[Assert\All([new Assert\Type('array')])]
    public ?array $questions = null;

    #[Assert\Callback(groups: ['create'])]
    public function validateCreate(ExecutionContextInterface $context): void
    {
        foreach (['organization_id', 'questionnaire_id', 'name', 'max_follow_ups', 'type', 'questions'] as $field) {
            if (null === $this->{$field}) {
                $context->buildViolation('This value should not be null.')->atPath($field)->addViolation();
            }
        }
        if (null !== $this->due_date && 'follow_up' !== $this->type) {
            $context->buildViolation('Only follow-ups have a due date.')->atPath('due_date')->addViolation();
        }
    }

    #[Assert\Callback(groups: ['update'])]
    public function validateUpdate(ExecutionContextInterface $context): void
    {
        if ([] === $this->providedFields()) {
            $context->buildViolation('Send at least one field to change.')->addViolation();
        }
        if ($this->wasProvided('type')) {
            $context->buildViolation('type cannot be changed')->atPath('type')->addViolation();
        }
        foreach (['organization_id', 'questionnaire_id', 'name', 'max_follow_ups', 'active', 'audience', 'questions'] as $field) {
            if ($this->wasProvided($field) && null === $this->{$field}) {
                $context->buildViolation('This value should not be null.')->atPath($field)->addViolation();
            }
        }
    }

    /** @return array<string, mixed> the fields that were sent (create: all of them, null ones left out) */
    public function fields(): array
    {
        $fields = [];
        foreach (self::FIELDS as $field) {
            if ($this->wasProvided($field)) {
                $fields[$field] = $this->{$field};
            }
        }

        return $fields;
    }
}
