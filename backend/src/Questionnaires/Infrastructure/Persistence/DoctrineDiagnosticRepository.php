<?php

namespace App\Questionnaires\Infrastructure\Persistence;

use App\Questionnaires\Domain\Model\Diagnostic;
use App\Questionnaires\Domain\Repository\DiagnosticRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<Diagnostic> */
final class DoctrineDiagnosticRepository extends DoctrineRepository implements DiagnosticRepository
{
    protected function entityClass(): string
    {
        return Diagnostic::class;
    }

    public function findByQuestionnaire(string $questionnaireId): ?Diagnostic
    {
        return $this->repository()->findOneBy(['questionnaireId' => $questionnaireId]);
    }

    public function add(Diagnostic $diagnostic): void
    {
        $this->persist($diagnostic);
    }

    public function remove(Diagnostic $diagnostic): void
    {
        $this->delete($diagnostic);
    }
}
