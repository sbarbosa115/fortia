<?php

declare(strict_types=1);

namespace App\Assignations\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Which session a member answered an assignation with (PRD §6.15). Written when the first stage is submitted. */
#[ORM\Entity]
#[ORM\Table(name: 'assignation_answer')]
class AssignationAnswer
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $assignationsId,
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $organizationUserId,
        #[ORM\Column(length: 36)]
        private string $sessionId,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function assignationsId(): string
    {
        return $this->assignationsId;
    }

    public function organizationUserId(): string
    {
        return $this->organizationUserId;
    }

    public function sessionId(): string
    {
        return $this->sessionId;
    }

    public function repointTo(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
