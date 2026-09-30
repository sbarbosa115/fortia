<?php

namespace App\Shared\UI\Http\Output\Document;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * A question (PRD §6.5). improvement_message, flagged_answer and review exist only in a session.
 */
final class QuestionOutput
{
    public const THEMES = ['gender', 'quote', 'weight', 'height', 'celebration', 'user-capture-data', 'jeans-size', 'weight-composite', 'organization-users-login'];

    /**
     * @param list<string>             $visibility
     * @param list<InputControlOutput> $options
     * @param list<string>             $acceptance_criteria
     */
    public function __construct(
        public readonly string $id,
        public readonly int $order,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $disclaimer,
        #[OA\Property(enum: self::THEMES, nullable: true)]
        public readonly ?string $theme_name,
        #[OA\Property(nullable: true)]
        public readonly mixed $statements,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string', enum: ['male', 'female']))]
        public readonly array $visibility,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: InputControlOutput::class)))]
        public readonly array $options,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string'), maxItems: 10)]
        public readonly array $acceptance_criteria,
        public readonly ?int $max_followups,
        public readonly ?bool $attachment_required,
        public readonly ?string $category,
        public readonly bool $required,
        public readonly ?string $improvement_message = null,
        #[OA\Property(nullable: true)]
        public readonly mixed $flagged_answer = null,
        public readonly ?ReviewOutput $review = null,
    ) {
    }

    /** @param array<string, mixed> $q a question normalized by Shared\Domain\Document\Questions */
    public static function fromArray(array $q): self
    {
        return new self(
            (string) ($q['id'] ?? ''),
            (int) ($q['order'] ?? 0),
            (string) ($q['title'] ?? ''),
            isset($q['description']) ? (string) $q['description'] : null,
            isset($q['disclaimer']) ? (string) $q['disclaimer'] : null,
            isset($q['theme_name']) ? (string) $q['theme_name'] : null,
            $q['statements'] ?? null,
            array_values((array) ($q['visibility'] ?? [])),
            array_map(static fn (array $c): InputControlOutput => InputControlOutput::fromArray($c), array_values(array_filter((array) ($q['options'] ?? []), 'is_array'))),
            array_values((array) ($q['acceptance_criteria'] ?? [])),
            isset($q['max_followups']) ? (int) $q['max_followups'] : null,
            isset($q['attachment_required']) ? (bool) $q['attachment_required'] : null,
            isset($q['category']) ? (string) $q['category'] : null,
            (bool) ($q['required'] ?? true),
            isset($q['improvement_message']) ? (string) $q['improvement_message'] : null,
            $q['flagged_answer'] ?? null,
            \is_array($q['review'] ?? null) ? ReviewOutput::fromArray($q['review']) : null,
        );
    }

    /**
     * @param array<int|string, mixed> $questions
     *
     * @return list<self>
     */
    public static function list(array $questions): array
    {
        return array_map(static fn (array $q): self => self::fromArray($q), array_values(array_filter($questions, 'is_array')));
    }
}
