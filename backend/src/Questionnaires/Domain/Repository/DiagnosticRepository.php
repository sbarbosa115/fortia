<?php

namespace App\Questionnaires\Domain\Repository;

use App\Questionnaires\Domain\Model\Diagnostic;

interface DiagnosticRepository
{
    public function findByQuestionnaire(string $questionnaireId): ?Diagnostic;

    public function add(Diagnostic $diagnostic): void;

    public function remove(Diagnostic $diagnostic): void;
}
