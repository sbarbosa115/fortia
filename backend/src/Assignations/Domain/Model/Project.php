<?php

namespace App\Assignations\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A group of follow-up assignations of one organization, with a due date (PRD §6.16). The organization never
 * changes. The assignations point to it (Assignation::$projectId).
 */
#[ORM\Entity]
#[ORM\Table(name: 'project')]
#[ORM\Index(name: 'idx_project_customer', columns: ['customer_id', 'created_at'])]
class Project
{
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** Its follow-ups go to review once complete; without it they are simply completed. */
    #[ORM\Column(options: ['default' => true])]
    private bool $requiresReview = true;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $projectId,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 36)]
        private string $organizationId,
        #[ORM\Column(length: 200)]
        private string $name,
        #[ORM\Column(length: 10, nullable: true)]
        private ?string $dueDate,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
    }

    public function change(string $name, ?string $description, string $dueDate, \DateTimeImmutable $at): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->dueDate = $dueDate;
        $this->updatedAt = $at;
    }

    public function requireReview(bool $requiresReview, \DateTimeImmutable $at): void
    {
        $this->requiresReview = $requiresReview;
        $this->updatedAt = $at;
    }

    public function requiresReview(): bool
    {
        return $this->requiresReview;
    }

    public function projectId(): string
    {
        return $this->projectId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function dueDate(): ?string
    {
        return $this->dueDate;
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
