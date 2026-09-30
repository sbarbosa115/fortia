<?php

namespace App\Billing\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** A feature of the global catalog (PRD §6.3). Its id is the slug of its name and never changes. */
#[ORM\Entity]
#[ORM\Table(name: 'feature')]
class Feature
{
    /** The canonical slugs. */
    public const REGULAR = 'regular';
    public const DIAGNOSTIC = 'diagnostic';
    public const QUIZ_FUNNEL = 'quiz-funnel';
    public const CHAIN = 'chain';
    public const CHAT = 'chat';
    public const ORGANIZATIONS = 'organizations';
    public const ASSIGNATIONS = 'assignations';
    public const STYLES = 'styles';
    public const ANALYTICS = 'analytics';
    public const DASHBOARDS = 'dashboards';
    public const USERS = 'users';
    public const API = 'api';
    public const WEBHOOK = 'webhook';
    public const PROFILE = 'profile';
    public const RESPONSES = 'responses';

    /** The features whose counters add up to max_questionnaires (PRD §7.1). */
    public const QUESTIONNAIRE_FEATURES = [self::REGULAR, self::DIAGNOSTIC, self::QUIZ_FUNNEL, self::CHAIN, self::CHAT];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 100)]
        private string $id,
        #[ORM\Column(length: 100)]
        private string $featureName,
        #[ORM\Column(type: Types::TEXT)]
        private string $featureDescription,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function featureName(): string
    {
        return $this->featureName;
    }

    public function featureDescription(): string
    {
        return $this->featureDescription;
    }

    /** Full replacement (PUT); the id does not change. */
    public function replace(string $name, string $description, \DateTimeImmutable $at): void
    {
        $this->featureName = $name;
        $this->featureDescription = $description;
        $this->updatedAt = $at;
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
