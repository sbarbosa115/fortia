<?php

namespace App\Responses\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * What was computed when a session was submitted (PRD §6.10): recommended products, a diagnostic result, an AI team
 * profile, or a client-specific result (samurai8, livingood) in $extra.
 */
#[ORM\Entity]
#[ORM\Table(name: 'session_results')]
#[ORM\Index(name: 'idx_results_questionnaire', columns: ['questionnaire_id'])]
class SessionResults
{
    /** @var list<array<string, mixed>>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $products = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $aiTeamProfile = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $diagnostic = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $extra = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $sessionId,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 36)]
        private string $questionnaireId,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    /** @param list<array<string, mixed>> $products */
    public function recordProducts(array $products): void
    {
        $this->products = $products;
    }

    /** @param array<string, mixed> $diagnostic */
    public function recordDiagnostic(array $diagnostic): void
    {
        $this->diagnostic = $diagnostic;
    }

    /** @param array<string, mixed> $profile */
    public function recordAiTeamProfile(array $profile): void
    {
        $this->aiTeamProfile = $profile;
    }

    /** @param array<string, mixed> $extra */
    public function recordExtra(array $extra): void
    {
        $this->extra = $extra;
    }

    public function sessionId(): string
    {
        return $this->sessionId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function questionnaireId(): string
    {
        return $this->questionnaireId;
    }

    /** @return list<array<string, mixed>>|null */
    public function products(): ?array
    {
        return $this->products;
    }

    /** @return array<string, mixed>|null */
    public function aiTeamProfile(): ?array
    {
        return $this->aiTeamProfile;
    }

    /** @return array<string, mixed>|null */
    public function diagnostic(): ?array
    {
        return $this->diagnostic;
    }

    /** @return array<string, mixed>|null */
    public function extra(): ?array
    {
        return $this->extra;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
