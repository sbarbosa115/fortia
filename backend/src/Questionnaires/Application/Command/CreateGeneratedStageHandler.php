<?php

namespace App\Questionnaires\Application\Command;

use App\Questionnaires\Domain\Flow\DiagnosticRules;
use App\Questionnaires\Domain\Flow\QuestionList;
use App\Questionnaires\Domain\Model\Diagnostic;
use App\Questionnaires\Domain\Model\Questionnaire;
use App\Questionnaires\Domain\Repository\DiagnosticRepository;
use App\Questionnaires\Domain\Repository\QuestionnaireRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateGeneratedStageHandler
{
    public function __construct(
        private readonly QuestionnaireRepository $questionnaires,
        private readonly DiagnosticRepository $diagnostics,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CreateGeneratedStage $command): string
    {
        $root = $this->questionnaires->get($command->rootQuestionnaireId);
        $rootId = $root->isRoot() ? $root->questionnaireId() : $root->parent();
        $now = $this->clock->now();

        $onCompleted = $command->onCompleted;
        if (\is_array($onCompleted)) {
            unset($onCompleted['tiers'], $onCompleted['recommendations'], $onCompleted['action_plan']);
        }
        $type = 'diagnostic' === ($onCompleted['type'] ?? null) || null !== $command->diagnostic ? 'diagnostic' : 'default';

        $id = Ids::uuid4();
        $stage = new Questionnaire($id, $root->customerId(), $command->title, $type, QuestionList::prepare($command->questions), $now);
        $stage->describe([
            'description' => $command->description,
            'capture_user_data' => $root->captureUserData(),
            'on_completed' => $onCompleted ?? (null !== $command->diagnostic ? ['type' => 'diagnostic'] : null),
        ], $now);
        $stage->makeStageOf($rootId, $command->originSessionId);
        $this->questionnaires->add($stage);

        if (null !== $command->diagnostic) {
            $diagnostic = DiagnosticRules::normalize($command->diagnostic);
            $this->diagnostics->add(new Diagnostic(Ids::uuid4(), $id, $diagnostic['tiers'], $diagnostic['recommendations'], $diagnostic['action_plan']));
        }

        return $id;
    }
}
