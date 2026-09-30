<?php

declare(strict_types=1);

namespace App\Questionnaires\Application\Query;

/**
 * A questionnaire as other contexts read it: $data has the PRD §6.5 shape (questionnaire_id, customer_id, title,
 * description, disclaimer, capture_user_data, landing_page, type, is_active, on_completed, parent,
 * origin_session_id, questions, question_count, is_chain, slug, created_at, updated_at).
 */
final class QuestionnaireView
{
    /** @param array<string, mixed> $data */
    public function __construct(public readonly array $data)
    {
    }

    public function id(): string
    {
        return (string) $this->data['questionnaire_id'];
    }

    public function customerId(): string
    {
        return (string) $this->data['customer_id'];
    }

    public function title(): string
    {
        return (string) $this->data['title'];
    }

    public function type(): string
    {
        return (string) $this->data['type'];
    }

    public function isActive(): bool
    {
        return (bool) $this->data['is_active'];
    }

    public function isRoot(): bool
    {
        return 'ROOT' === $this->data['parent'];
    }

    public function parent(): string
    {
        return (string) $this->data['parent'];
    }

    /** @return list<array<string, mixed>> */
    public function questions(): array
    {
        return array_values((array) $this->data['questions']);
    }

    /** @return array<string, mixed>|null */
    public function onCompleted(): ?array
    {
        return \is_array($this->data['on_completed'] ?? null) ? $this->data['on_completed'] : null;
    }
}
