<?php

namespace App\Identity\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A console user (PRD §6.2), the identity provider's user record: email (globally unique, the username), name,
 * customer_id, root and groups. The password is a hash; a user who only signs in with Google has none.
 */
#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ORM\Index(name: 'idx_user_customer', columns: ['customer_id'])]
class User
{
    public const GROUPS = ['Admin', 'Customer-Admin', 'Customer-Read-Only'];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordHash = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleSubject = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSignedInAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param list<string> $groups
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $id,
        #[ORM\Column(length: 180)]
        private string $email,
        #[ORM\Column(length: 50)]
        private string $name,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column]
        private bool $root,
        #[ORM\Column(name: 'user_groups', type: Types::JSON)]
        private array $groups,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->email = mb_strtolower(trim($email));
        $this->updatedAt = $createdAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function isRoot(): bool
    {
        return $this->root;
    }

    /** @return list<string> */
    public function groups(): array
    {
        return $this->groups;
    }

    public function isAdmin(): bool
    {
        return \in_array('Admin', $this->groups, true);
    }

    /** Admin > Customer-Admin > Customer-Read-Only (PRD §4.2). */
    public function displayedRole(): string
    {
        foreach (self::GROUPS as $group) {
            if (\in_array($group, $this->groups, true)) {
                return $group;
            }
        }

        return 'Customer-Read-Only';
    }

    public function passwordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $hash, \DateTimeImmutable $at): void
    {
        $this->passwordHash = $hash;
        $this->updatedAt = $at;
    }

    public function googleSubject(): ?string
    {
        return $this->googleSubject;
    }

    public function linkGoogle(string $subject, \DateTimeImmutable $at): void
    {
        $this->googleSubject = $subject;
        $this->updatedAt = $at;
    }

    public function recordSignIn(\DateTimeImmutable $at): void
    {
        $this->lastSignedInAt = $at;
    }

    public function lastSignedInAt(): ?\DateTimeImmutable
    {
        return $this->lastSignedInAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
