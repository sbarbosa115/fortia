<?php

namespace App\Questionnaires\Domain\Repository;

use App\Questionnaires\Domain\Model\Prompt;

interface PromptRepository
{
    /** @return list<Prompt> in order */
    public function listByQuestionnaire(string $questionnaireId): array;

    public function add(Prompt $prompt): void;

    public function remove(Prompt $prompt): void;
}
