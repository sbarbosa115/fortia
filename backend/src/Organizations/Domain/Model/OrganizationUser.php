<?php

namespace App\Organizations\Domain\Model;

use App\Shared\Domain\Text;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A member of an organization (PRD §6.13). The name is normalized (lowercase, no accents, single spaces), the email
 * lowercase, the phone digits with an optional leading "+". A member needs an email or a phone; the email is unique
 * within the organization.
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization_user')]
#[ORM\UniqueConstraint(name: 'uniq_member_email', columns: ['organization_id', 'email'])]
#[ORM\Index(name: 'idx_member_organization', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_member_email', columns: ['email'])]
#[ORM\Index(name: 'idx_member_phone', columns: ['phone'])]
class OrganizationUser
{
    #[ORM\Column(length: 200)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $area = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $organizationUserId,
        #[ORM\Column(length: 36)]
        private string $organizationId,
        string $name,
        ?string $email,
        ?string $phone,
        ?string $role,
        ?string $area,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->name = '';
        $this->updatedAt = $createdAt;
        $this->change($name, $email, $phone, $role, $area, $createdAt);
    }

    public function change(string $name, ?string $email, ?string $phone, ?string $role, ?string $area, \DateTimeImmutable $at): void
    {
        $this->name = Text::fold($name);
        $email = null === $email ? '' : mb_strtolower(trim($email));
        $this->email = '' === $email ? null : $email;
        $phone = null === $phone ? '' : Text::normalizePhone($phone);
        $this->phone = '' === $phone || '+' === $phone ? null : $phone;
        $this->role = null === $role || '' === trim($role) ? null : trim($role);
        $this->area = null === $area || '' === trim($area) ? null : trim($area);
        $this->updatedAt = $at;
    }

    public function organizationUserId(): string
    {
        return $this->organizationUserId;
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function role(): ?string
    {
        return $this->role;
    }

    public function area(): ?string
    {
        return $this->area;
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
