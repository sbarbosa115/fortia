<?php

declare(strict_types=1);

namespace App\Assignations\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Sending a questionnaire to an organization (PRD §6.14), with an audience and a type (default | follow_up, which
 * never changes). $questions is the registration slide (the respondent login), not the questionnaire.
 *
 * Server-managed: project_id, shared_session_id, attempts [{number, session_id, created_at}], last_reminder_sent_at.
 */
#[ORM\Entity]
#[ORM\Table(name: 'assignation')]
#[ORM\Index(name: 'idx_assignation_customer', columns: ['customer_id', 'created_at'])]
#[ORM\Index(name: 'idx_assignation_questionnaire', columns: ['questionnaire_id'])]
#[ORM\Index(name: 'idx_assignation_project', columns: ['project_id'])]
#[ORM\Index(name: 'idx_assignation_organization', columns: ['organization_id'])]
class Assignation
{
    public const DEFAULT = 'default';
    public const FOLLOW_UP = 'follow_up';
    public const AUDIENCE_TYPES = ['all', 'members', 'area', 'role'];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $maxFollowUps = 0;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $dueDate = null;

    /** @var array{type: string, values: list<string>} */
    #[ORM\Column(type: Types::JSON)]
    private array $audience = ['type' => 'all', 'values' => []];

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: Types::JSON)]
    private array $questions = [];

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $projectId = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $sharedSessionId = null;

    /** @var list<array{number: int, session_id: string, created_at: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $attempts = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastReminderSentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $assignationsId,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 36)]
        private string $organizationId,
        #[ORM\Column(length: 36)]
        private string $questionnaireId,
        #[ORM\Column(length: 200)]
        private string $name,
        #[ORM\Column(length: 10)]
        private string $type,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
    }

    /**
     * The editable fields (PRD §8.8 POST/PUT). The type is not one of them.
     *
     * @param array{type: string, values: list<string>} $audience
     * @param list<array<string, mixed>>                $questions
     */
    public function configure(
        string $organizationId,
        string $questionnaireId,
        string $name,
        ?string $description,
        int $maxFollowUps,
        bool $active,
        ?string $dueDate,
        array $audience,
        array $questions,
        \DateTimeImmutable $at,
    ): void {
        $this->organizationId = $organizationId;
        $this->questionnaireId = $questionnaireId;
        $this->name = $name;
        $this->description = $description;
        $this->maxFollowUps = $maxFollowUps;
        $this->active = $active;
        $this->dueDate = self::FOLLOW_UP === $this->type ? $dueDate : null;
        $this->audience = $audience;
        $this->questions = $questions;
        $this->updatedAt = $at;
    }

    public function setActive(bool $active, \DateTimeImmutable $at): void
    {
        $this->active = $active;
        $this->updatedAt = $at;
    }

    public function joinProject(?string $projectId, \DateTimeImmutable $at): void
    {
        $this->projectId = $projectId;
        $this->updatedAt = $at;
    }

    /** A follow-up's shared session and a new attempt (attempt 1 on the first login, n+1 on a retry). */
    public function startAttempt(string $sessionId, \DateTimeImmutable $at): int
    {
        $number = \count($this->attempts) + 1;
        $this->attempts[] = ['number' => $number, 'session_id' => $sessionId, 'created_at' => $at->format('Y-m-d\TH:i:s\Z')];
        $this->sharedSessionId = $sessionId;
        $this->lastReminderSentAt = null;
        $this->updatedAt = $at;

        return $number;
    }

    public function markReminded(\DateTimeImmutable $at): void
    {
        $this->lastReminderSentAt = $at;
    }

    public function isFollowUp(): bool
    {
        return self::FOLLOW_UP === $this->type;
    }

    public function assignationsId(): string
    {
        return $this->assignationsId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function questionnaireId(): string
    {
        return $this->questionnaireId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function maxFollowUps(): int
    {
        return $this->maxFollowUps;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function dueDate(): ?string
    {
        return $this->dueDate;
    }

    /** @return array{type: string, values: list<string>} */
    public function audience(): array
    {
        return $this->audience;
    }

    /** @return list<array<string, mixed>> */
    public function questions(): array
    {
        return $this->questions;
    }

    public function projectId(): ?string
    {
        return $this->projectId;
    }

    public function sharedSessionId(): ?string
    {
        return $this->sharedSessionId;
    }

    /** @return list<array{number: int, session_id: string, created_at: string}> */
    public function attempts(): array
    {
        return $this->attempts;
    }

    public function currentAttempt(): int
    {
        return max(1, \count($this->attempts));
    }

    public function lastReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->lastReminderSentAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
