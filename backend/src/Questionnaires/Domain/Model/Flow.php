<?php

declare(strict_types=1);

namespace App\Questionnaires\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The state machine that wraps a questionnaire (PRD §6.6): its entry point, chained stages and result. One flow per
 * questionnaire; its slug is unique across the whole system.
 *
 * A state is {state_id (15 chars), type, parameters{}, outputs{}, next?}.
 */
#[ORM\Entity]
#[ORM\Table(name: 'flow')]
#[ORM\UniqueConstraint(name: 'uniq_flow_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_flow_questionnaire', columns: ['questionnaire_id'])]
#[ORM\Index(name: 'idx_flow_source_url', columns: ['source_url'])]
class Flow
{
    public const STATE_TYPES = ['questionnaire', 'regular', 'quiz_funnel', 'diagnostic', 'prompt', 'result'];
    public const LAYOUT_BLOCKS = ['score', 'tier', 'categories', 'recommendations', 'action_plan', 'pdf', 'cta'];
    public const RESULT_COPY_KEYS = [
        'eyebrow', 'title', 'subtitle', 'tier_label', 'overall_score', 'categories_title', 'categories_subtitle',
        'chart_title', 'chart_subtitle', 'chart_legend', 'recommendations', 'action_plan', 'report_title',
        'report_subtitle', 'download',
    ];

    #[ORM\Column(type: Types::TEXT)]
    private string $detail = '';

    #[ORM\Column(length: 768, nullable: true)]
    private ?string $sourceUrl = null;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: Types::JSON)]
    private array $states = [];

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $cta = null;

    /** @var list<string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $layout = null;

    /** @var array<string, string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $resultCopy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param list<array<string, mixed>> $states
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 20)]
        private string $id,
        #[ORM\Column(length: 100)]
        private string $slug,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 36)]
        private string $questionnaireId,
        array $states,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->states = $states;
        $this->updatedAt = $createdAt;
    }

    /**
     * Replaces what an edit sends (PRD §7.5: "the flow keeps its id").
     *
     * @param list<array<string, mixed>>  $states
     * @param array<string, mixed>|null   $cta
     * @param list<string>|null           $layout
     * @param array<string, string>|null  $resultCopy
     */
    public function redefine(string $slug, array $states, ?array $cta, ?array $layout, ?array $resultCopy, \DateTimeImmutable $at): void
    {
        $this->slug = $slug;
        $this->states = $states;
        $this->cta = $cta;
        $this->layout = $layout;
        $this->resultCopy = $resultCopy;
        $this->updatedAt = $at;
    }

    public function setDetail(string $detail): void
    {
        $this->detail = $detail;
    }

    public function setSourceUrl(?string $sourceUrl): void
    {
        $this->sourceUrl = $sourceUrl;
    }

    /**
     * The flow type shown in listings: the first special state present, in the order prompt → diagnostic →
     * quiz_funnel, else "default" (PRD §6.6).
     */
    public function displayedType(): string
    {
        $types = array_column($this->states, 'type');
        foreach (['prompt', 'diagnostic', 'quiz_funnel'] as $type) {
            if (\in_array($type, $types, true)) {
                return $type;
            }
        }

        return 'default';
    }

    public function isChain(): bool
    {
        return \in_array('prompt', array_column($this->states, 'type'), true);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function detail(): string
    {
        return $this->detail;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function questionnaireId(): string
    {
        return $this->questionnaireId;
    }

    public function sourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    /** @return list<array<string, mixed>> */
    public function states(): array
    {
        return $this->states;
    }

    /** @return array<string, mixed>|null */
    public function cta(): ?array
    {
        return $this->cta;
    }

    /** @return list<string>|null */
    public function layout(): ?array
    {
        return $this->layout;
    }

    /** @return array<string, string>|null */
    public function resultCopy(): ?array
    {
        return $this->resultCopy;
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
