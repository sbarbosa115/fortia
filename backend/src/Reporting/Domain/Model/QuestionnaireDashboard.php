<?php

declare(strict_types=1);

namespace App\Reporting\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The dashboard the LLM chose for a questionnaire (PRD §6.19): created once and kept forever.
 * Charts: [{id, chart_type, title, question_ids[], order}].
 */
#[ORM\Entity]
#[ORM\Table(name: 'questionnaire_dashboard')]
class QuestionnaireDashboard
{
    public const TYPES = ['satisfaction', 'knowledge', 'profiling', 'recommendations', 'eligibility', 'opinion'];
    public const CHART_TYPES = [
        'kpi', 'gauge', 'line', 'donut', 'bar', 'horizontal_bar', 'stacked_bar', 'treemap', 'histogram', 'boxplot',
        'ranking_avg', 'heatmap', 'tier_distribution',
    ];

    /**
     * @param list<array<string, mixed>> $charts
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $questionnaireId,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 20)]
        private string $type,
        #[ORM\Column(type: Types::JSON)]
        private array $charts,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function questionnaireId(): string
    {
        return $this->questionnaireId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function type(): string
    {
        return $this->type;
    }

    /** @return list<array<string, mixed>> */
    public function charts(): array
    {
        return $this->charts;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
