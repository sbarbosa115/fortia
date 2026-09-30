<?php

namespace App\Questionnaires\Application\Command;

use App\Questionnaires\Domain\Repository\QuestionnaireRepository;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class SetQuestionnaireActiveHandler
{
    public function __construct(
        private readonly QuestionnaireRepository $questionnaires,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SetQuestionnaireActive $command): void
    {
        $this->questionnaires->get($command->questionnaireId)->setActive($command->active, $this->clock->now());
    }
}
