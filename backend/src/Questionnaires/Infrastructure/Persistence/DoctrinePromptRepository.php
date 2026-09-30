<?php

namespace App\Questionnaires\Infrastructure\Persistence;

use App\Questionnaires\Domain\Model\Prompt;
use App\Questionnaires\Domain\Repository\PromptRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<Prompt> */
final class DoctrinePromptRepository extends DoctrineRepository implements PromptRepository
{
    protected function entityClass(): string
    {
        return Prompt::class;
    }

    public function listByQuestionnaire(string $questionnaireId): array
    {
        return $this->repository()->findBy(['questionnaireId' => $questionnaireId], ['order' => 'ASC']);
    }

    public function add(Prompt $prompt): void
    {
        $this->persist($prompt);
    }

    public function remove(Prompt $prompt): void
    {
        $this->delete($prompt);
    }
}
