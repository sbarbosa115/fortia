<?php

namespace App\Questionnaires\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * A prompt of a chain (PRD §6.8): the text lives in object storage at $s3Path (prompts/{customer_id}/{uuid}.txt);
 * $outcome is the next terminal state after it (diagnostic, quiz_funnel or result).
 */
#[ORM\Entity]
#[ORM\Table(name: 'prompt')]
#[ORM\Index(name: 'idx_prompt_questionnaire', columns: ['questionnaire_id', 'prompt_order'])]
class Prompt
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $id,
        #[ORM\Column(length: 36)]
        private string $questionnaireId,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 1024)]
        private string $s3Path,
        #[ORM\Column(length: 20, nullable: true)]
        private ?string $outcome = null,
        #[ORM\Column(name: 'prompt_order')]
        private int $order = 0,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function questionnaireId(): string
    {
        return $this->questionnaireId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function s3Path(): string
    {
        return $this->s3Path;
    }

    public function outcome(): ?string
    {
        return $this->outcome;
    }

    public function order(): int
    {
        return $this->order;
    }
}
