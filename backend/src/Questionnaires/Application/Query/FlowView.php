<?php

namespace App\Questionnaires\Application\Query;

/**
 * A flow as other contexts read it: $data has the shape of PRD §8.4 GET /flow (id, slug, detail, states,
 * customer_id, questionnaire_id, source_url, cta, layout, result_copy, created_at, updated_at).
 */
final class FlowView
{
    /** @param array<string, mixed> $data */
    public function __construct(public readonly array $data)
    {
    }

    public function id(): string
    {
        return (string) $this->data['id'];
    }

    public function slug(): string
    {
        return (string) $this->data['slug'];
    }

    public function questionnaireId(): string
    {
        return (string) $this->data['questionnaire_id'];
    }

    public function customerId(): string
    {
        return (string) $this->data['customer_id'];
    }

    /** @return list<array<string, mixed>> */
    public function states(): array
    {
        return array_values((array) $this->data['states']);
    }

    /** @return array<string, mixed>|null */
    public function cta(): ?array
    {
        return \is_array($this->data['cta'] ?? null) ? $this->data['cta'] : null;
    }

    /** @return list<string>|null */
    public function layout(): ?array
    {
        return \is_array($this->data['layout'] ?? null) ? array_values($this->data['layout']) : null;
    }

    /** @return array<string, string>|null */
    public function resultCopy(): ?array
    {
        return \is_array($this->data['result_copy'] ?? null) ? $this->data['result_copy'] : null;
    }

    /**
     * The first state of a type, e.g. the single "questionnaire" state.
     *
     * @return array<string, mixed>|null
     */
    public function stateOfType(string $type): ?array
    {
        foreach ($this->states() as $state) {
            if (($state['type'] ?? null) === $type) {
                return $state;
            }
        }

        return null;
    }
}
