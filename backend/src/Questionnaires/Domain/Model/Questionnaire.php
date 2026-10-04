<?php

namespace App\Questionnaires\Domain\Model;

use App\Shared\Domain\Document\QuestionnaireTags;
use App\Shared\Domain\Document\Questions;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A questionnaire ("experience", PRD §6.5): an ordered set of questions with a behaviour on completion. The
 * questions are a JSON document (Shared\Domain\Document\Questions). The generated stages of a chain point to their
 * root through $parent and to the session that produced them through $originSessionId.
 *
 * question_count, is_chain and slug are denormalized copies for listings. Tags are the owner's free-text labels
 * (Shared\Domain\Document\QuestionnaireTags).
 */
#[ORM\Entity]
#[ORM\Table(name: 'questionnaire')]
#[ORM\Index(name: 'idx_questionnaire_customer', columns: ['customer_id', 'parent', 'created_at'])]
#[ORM\Index(name: 'idx_questionnaire_parent', columns: ['parent'])]
#[ORM\Index(name: 'idx_questionnaire_origin_session', columns: ['origin_session_id'])]
class Questionnaire
{
    public const ROOT = 'ROOT';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $disclaimer = null;

    #[ORM\Column]
    private bool $captureUserData = false;

    #[ORM\Column]
    private bool $landingPage = false;

    #[ORM\Column]
    private bool $isActive = true;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $onCompleted = null;

    #[ORM\Column(length: 36)]
    private string $parent = self::ROOT;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $originSessionId = null;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: Types::JSON)]
    private array $questions = [];

    #[ORM\Column]
    private int $questionCount = 0;

    #[ORM\Column]
    private bool $isChain = false;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $slug = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param list<array<string, mixed>> $questions
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $questionnaireId,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(type: Types::TEXT)]
        private string $title,
        #[ORM\Column(length: 20)]
        private string $type,
        array $questions,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
        $this->replaceQuestions($questions, $createdAt);
    }

    /**
     * The fields a flow's "questionnaire" state carries (PRD §8.4 POST/PUT /questionnaire). Keys that are absent keep
     * their value.
     *
     * @param array<string, mixed> $fields
     */
    public function describe(array $fields, \DateTimeImmutable $at): void
    {
        if (\array_key_exists('title', $fields)) {
            $this->title = (string) $fields['title'];
        }
        if (\array_key_exists('description', $fields)) {
            $this->description = self::nullableText($fields['description']);
        }
        if (\array_key_exists('disclaimer', $fields)) {
            $this->disclaimer = self::nullableText($fields['disclaimer']);
        }
        if (\array_key_exists('capture_user_data', $fields)) {
            $this->captureUserData = (bool) $fields['capture_user_data'];
        }
        if (\array_key_exists('landing_page', $fields)) {
            $this->landingPage = (bool) $fields['landing_page'];
        }
        if (\array_key_exists('type', $fields) && \is_string($fields['type'])) {
            $this->type = $fields['type'];
        }
        if (\array_key_exists('on_completed', $fields)) {
            $this->onCompleted = \is_array($fields['on_completed']) ? $fields['on_completed'] : null;
        }
        $this->updatedAt = $at;
    }

    /** @param list<array<string, mixed>> $questions */
    public function replaceQuestions(array $questions, \DateTimeImmutable $at): void
    {
        $this->questions = Questions::withoutRuntime($questions);
        $this->questionCount = \count($this->questions);
        $this->updatedAt = $at;
    }

    /** A generated stage of a chain (PRD §7.8): it points to its root and to the session that produced it. */
    public function makeStageOf(string $rootQuestionnaireId, ?string $originSessionId): void
    {
        $this->parent = $rootQuestionnaireId;
        $this->originSessionId = $originSessionId;
    }

    /**
     * Replaces the tags, normalized (trimmed, without empties or repeats).
     *
     * @throws \App\Shared\Domain\Error\Rejected VALIDATION_ERROR: more than 20 tags, or one over 40 characters
     */
    public function retag(mixed $tags, \DateTimeImmutable $at): void
    {
        $this->tags = QuestionnaireTags::normalize($tags);
        $this->updatedAt = $at;
    }

    public function setActive(bool $active, \DateTimeImmutable $at): void
    {
        $this->isActive = $active;
        $this->updatedAt = $at;
    }

    /** Keeps the listing copies of the flow in step (slug, is_chain). */
    public function syncFlowCopies(?string $slug, bool $isChain): void
    {
        $this->slug = $slug;
        $this->isChain = $isChain;
    }

    public function questionnaireId(): string
    {
        return $this->questionnaireId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function disclaimer(): ?string
    {
        return $this->disclaimer;
    }

    public function captureUserData(): bool
    {
        return $this->captureUserData;
    }

    public function landingPage(): bool
    {
        return $this->landingPage;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /** @return array<string, mixed>|null */
    public function onCompleted(): ?array
    {
        return $this->onCompleted;
    }

    public function parent(): string
    {
        return $this->parent;
    }

    public function isRoot(): bool
    {
        return self::ROOT === $this->parent;
    }

    public function originSessionId(): ?string
    {
        return $this->originSessionId;
    }

    /** @return list<array<string, mixed>> */
    public function questions(): array
    {
        return $this->questions;
    }

    public function questionCount(): int
    {
        return $this->questionCount;
    }

    public function isChain(): bool
    {
        return $this->isChain;
    }

    public function slug(): ?string
    {
        return $this->slug;
    }

    /** @return list<string> */
    public function tags(): array
    {
        return QuestionnaireTags::fromStored($this->tags);
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function nullableText(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? $value : null;
    }
}
