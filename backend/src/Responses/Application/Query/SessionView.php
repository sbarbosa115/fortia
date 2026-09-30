<?php

declare(strict_types=1);

namespace App\Responses\Application\Query;

/**
 * A session as other contexts read it: $data is the questionnaire copy (PRD §6.9) merged with the session fields
 * (session_id, questionnaire_id, customer_id, started_at, ended_at, flow_id, status, user_data, assignations_id,
 * organization_user_id, assignation_type, attempt, questions…).
 */
final class SessionView
{
    /** @param array<string, mixed> $data */
    public function __construct(public readonly array $data)
    {
    }

    public function id(): string
    {
        return (string) $this->data['session_id'];
    }

    public function questionnaireId(): string
    {
        return (string) $this->data['questionnaire_id'];
    }

    public function customerId(): string
    {
        return (string) $this->data['customer_id'];
    }

    public function status(): string
    {
        return (string) $this->data['status'];
    }

    public function isEnded(): bool
    {
        return null !== ($this->data['ended_at'] ?? null);
    }

    public function attempt(): int
    {
        return (int) ($this->data['attempt'] ?? 1);
    }

    /** @return list<array<string, mixed>> */
    public function questions(): array
    {
        return array_values((array) ($this->data['questions'] ?? []));
    }
}
