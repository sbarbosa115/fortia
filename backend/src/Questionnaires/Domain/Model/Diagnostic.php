<?php

namespace App\Questionnaires\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The scoring configuration of a diagnostic questionnaire (PRD §6.7): tiers {id, name, description?, min, max,
 * visible}, and per tier the recommendations {tier_id, recommendation, visible} and actions {tier_id, action,
 * visible}. Never shown to a respondent before they finish.
 */
#[ORM\Entity]
#[ORM\Table(name: 'diagnostic')]
#[ORM\UniqueConstraint(name: 'uniq_diagnostic_questionnaire', columns: ['questionnaire_id'])]
class Diagnostic
{
    /**
     * @param list<array<string, mixed>> $tiers
     * @param list<array<string, mixed>> $recommendations
     * @param list<array<string, mixed>> $actionPlan
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $id,
        #[ORM\Column(length: 36)]
        private string $questionnaireId,
        #[ORM\Column(type: Types::JSON)]
        private array $tiers,
        #[ORM\Column(type: Types::JSON)]
        private array $recommendations,
        #[ORM\Column(type: Types::JSON)]
        private array $actionPlan,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $tiers
     * @param list<array<string, mixed>> $recommendations
     * @param list<array<string, mixed>> $actionPlan
     */
    public function rebuild(array $tiers, array $recommendations, array $actionPlan): void
    {
        $this->tiers = $tiers;
        $this->recommendations = $recommendations;
        $this->actionPlan = $actionPlan;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function questionnaireId(): string
    {
        return $this->questionnaireId;
    }

    /** @return list<array<string, mixed>> */
    public function tiers(): array
    {
        return $this->tiers;
    }

    /** @return list<array<string, mixed>> */
    public function recommendations(): array
    {
        return $this->recommendations;
    }

    /** @return list<array<string, mixed>> */
    public function actionPlan(): array
    {
        return $this->actionPlan;
    }
}
