<?php

namespace App\Responses\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A response (PRD §6.9): a full copy of the questionnaire with the respondent's values, plus the session fields.
 * The copy is the JSON $document (title, description, disclaimer, type, landing_page, capture_user_data, parent,
 * on_completed reduced to {type}, questions with values); the session fields are columns.
 *
 * Status: filling → filled_out → processing → completed (legacy in_progress = filling, submitted = filled_out).
 */
#[ORM\Entity]
#[ORM\Table(name: 'questionnaire_session')]
#[ORM\Index(name: 'idx_session_questionnaire', columns: ['questionnaire_id', 'started_at'])]
#[ORM\Index(name: 'idx_session_assignation', columns: ['assignations_id', 'organization_user_id'])]
#[ORM\Index(name: 'idx_session_customer', columns: ['customer_id', 'started_at'])]
class QuestionnaireSession
{
    public const FILLING = 'filling';
    public const FILLED_OUT = 'filled_out';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const LEGACY_ALIASES = ['in_progress' => self::FILLING, 'submitted' => self::FILLED_OUT];

    #[ORM\Column(length: 12)]
    private string $status = self::FILLING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $flowId = null;

    /** @var array{name?: string, email?: string, phone?: string}|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $userData = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $assignationsId = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $organizationUserId = null;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $assignationType = null;

    #[ORM\Column]
    private int $attempt = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $document the questionnaire copy (see the class comment)
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $sessionId,
        #[ORM\Column(length: 36)]
        private string $questionnaireId,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(type: Types::JSON)]
        private array $document,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $startedAt,
    ) {
        $this->updatedAt = $startedAt;
    }

    public function attachFlow(?string $flowId): void
    {
        $this->flowId = $flowId;
    }

    /** An assignation's session (PRD §6.9): bound to the assignation, the member (or null when shared) and the attempt. */
    public function bindToAssignation(string $assignationsId, ?string $organizationUserId, ?string $assignationType, int $attempt): void
    {
        $this->assignationsId = $assignationsId;
        $this->organizationUserId = $organizationUserId;
        $this->assignationType = $assignationType;
        $this->attempt = $attempt;
    }

    /** @param array<string, mixed> $document */
    public function saveProgress(array $document, \DateTimeImmutable $at): void
    {
        $this->document = $document;
        $this->updatedAt = $at;
    }

    /** @param array{name?: string, email?: string, phone?: string}|null $userData */
    public function fillOut(?array $userData, \DateTimeImmutable $at): void
    {
        if (null !== $userData) {
            $this->userData = $userData;
        }
        $this->endedAt = $at;
        $this->status = self::FILLED_OUT;
        $this->updatedAt = $at;
    }

    /**
     * The result is computed by a job (quiz funnel recommendations). The job id stays in the document: a repeated
     * submission answers with that job, even once it is done.
     */
    public function markProcessing(\DateTimeImmutable $at): void
    {
        $this->status = self::PROCESSING;
        $this->updatedAt = $at;
    }

    public function trackProcessingJob(string $jobId): void
    {
        $this->document['processing_job_id'] = $jobId;
    }

    public function complete(\DateTimeImmutable $at): void
    {
        $this->status = self::COMPLETED;
        $this->updatedAt = $at;
    }

    /**
     * The questions with the respondent's latest values (SessionAnswers decides what the respondent may change).
     *
     * @param list<array<string, mixed>> $questions
     */
    public function answer(array $questions, \DateTimeImmutable $at): void
    {
        $this->document['questions'] = array_values($questions);
        $this->updatedAt = $at;
    }

    /** A follow-up's shared session (PRD §7.11): every member writes to it, and it closes once it has ended_at. */
    public function isFollowUp(): bool
    {
        return 'follow_up' === $this->assignationType;
    }

    public function isEnded(): bool
    {
        return null !== $this->endedAt;
    }

    public function processingJobId(): ?string
    {
        $jobId = $this->document['processing_job_id'] ?? null;

        return \is_string($jobId) ? $jobId : null;
    }

    public function sessionId(): string
    {
        return $this->sessionId;
    }

    public function questionnaireId(): string
    {
        return $this->questionnaireId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    /** @return array<string, mixed> */
    public function document(): array
    {
        return $this->document;
    }

    /** @return list<array<string, mixed>> */
    public function questions(): array
    {
        $questions = $this->document['questions'] ?? [];

        return \is_array($questions) ? array_values(array_filter($questions, 'is_array')) : [];
    }

    public function status(): string
    {
        return $this->status;
    }

    public function startedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function endedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function flowId(): ?string
    {
        return $this->flowId;
    }

    /** @return array{name?: string, email?: string, phone?: string}|null */
    public function userData(): ?array
    {
        return $this->userData;
    }

    public function assignationsId(): ?string
    {
        return $this->assignationsId;
    }

    public function organizationUserId(): ?string
    {
        return $this->organizationUserId;
    }

    public function assignationType(): ?string
    {
        return $this->assignationType;
    }

    public function attempt(): int
    {
        return $this->attempt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
