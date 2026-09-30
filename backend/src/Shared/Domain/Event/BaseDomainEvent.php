<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * The common shape of a domain event. A context's event extends it and names itself:
 *
 *     final class QuestionnaireCreated extends BaseDomainEvent
 *     {
 *         public static function of(string $customerId, string $questionnaireId, string $feature): self
 *         {
 *             return new self($customerId, $feature, ['questionnaire_id' => $questionnaireId]);
 *         }
 *     }
 */
abstract class BaseDomainEvent implements DomainEvent
{
    public readonly string $occurredAt;

    /**
     * @param array<string, mixed> $payload
     */
    final public function __construct(
        private readonly ?string $customerId,
        private readonly ?string $feature = null,
        private readonly array $payload = [],
    ) {
        $this->occurredAt = gmdate('Y-m-d\TH:i:s\Z');
    }

    public function eventType(): string
    {
        $class = static::class;

        return substr($class, (int) strrpos($class, '\\') + 1);
    }

    public function customerId(): ?string
    {
        return $this->customerId;
    }

    public function feature(): ?string
    {
        return $this->feature;
    }

    public function payload(): array
    {
        return $this->payload;
    }
}
