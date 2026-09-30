<?php

namespace App\Jobs\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Asynchronous work that can be polled (PRD §6.17, §5 A1): PENDING → PROCESSING → COMPLETED | FAILED | CANCELLED.
 * The payload is internal and never returned.
 */
#[ORM\Entity]
#[ORM\Table(name: 'job')]
#[ORM\Index(name: 'idx_job_type', columns: ['job_type', 'created_at'])]
#[ORM\Index(name: 'idx_job_status', columns: ['status', 'created_at'])]
class Job
{
    public const PENDING = 'PENDING';
    public const PROCESSING = 'PROCESSING';
    public const COMPLETED = 'COMPLETED';
    public const FAILED = 'FAILED';
    public const CANCELLED = 'CANCELLED';

    public const TYPES = [
        'styles', 'profile_customization', 'answer_evaluation', 'linkedin_questionnaire', 'prompt_questionnaire',
        'create_quiz_funnel', 'scrape_products', 'process_completed_session', 'chat', 'chat-questionnaire-created',
        'chat-questionnaire-drafted', 'chat-questionnaire-approved',
    ];

    #[ORM\Column(length: 12)]
    private string $status = self::PENDING;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $result = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $stage = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 40)]
        private string $jobId,
        #[ORM\Column(length: 40)]
        private string $jobType,
        #[ORM\Column(type: Types::JSON)]
        private array $payload,
        #[ORM\Column(length: 16, nullable: true)]
        private ?string $customerId,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
    }

    public function start(\DateTimeImmutable $at): void
    {
        $this->status = self::PROCESSING;
        $this->updatedAt = $at;
    }

    public function advance(string $stage, \DateTimeImmutable $at): void
    {
        $this->stage = $stage;
        $this->updatedAt = $at;
    }

    /** @param array<string, mixed> $result */
    public function complete(array $result, \DateTimeImmutable $at): void
    {
        $this->status = self::COMPLETED;
        $this->result = $result;
        $this->updatedAt = $at;
    }

    public function fail(string $type, string $message, \DateTimeImmutable $at): void
    {
        $this->status = self::FAILED;
        $this->result = ['error' => ['type' => $type, 'message' => $message]];
        $this->updatedAt = $at;
    }

    public function cancel(\DateTimeImmutable $at): void
    {
        $this->status = self::CANCELLED;
        $this->updatedAt = $at;
    }

    public function isFinished(): bool
    {
        return \in_array($this->status, [self::COMPLETED, self::FAILED, self::CANCELLED], true);
    }

    public function jobId(): string
    {
        return $this->jobId;
    }

    public function jobType(): string
    {
        return $this->jobType;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function customerId(): ?string
    {
        return $this->customerId;
    }

    public function status(): string
    {
        return $this->status;
    }

    /** @return array<string, mixed>|null */
    public function result(): ?array
    {
        return $this->result;
    }

    public function stage(): ?string
    {
        return $this->stage;
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
