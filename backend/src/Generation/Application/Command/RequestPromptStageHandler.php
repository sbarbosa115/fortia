<?php

namespace App\Generation\Application\Command;

use App\Generation\Application\ChainPosition;
use App\Generation\Application\Job\PromptStageJob;
use App\Jobs\Application\Jobs;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RequestPromptStageHandler
{
    public function __construct(
        private readonly ChainPosition $chain,
        private readonly Jobs $jobs,
    ) {
    }

    public function __invoke(RequestPromptStage $command): string
    {
        $step = $this->chain->resolve($command->questionnaireId, $command->sessionId);

        return $this->jobs->start(PromptStageJob::TYPE, [
            'root_questionnaire_id' => $step->rootQuestionnaireId,
            'stage_questionnaire_ids' => $step->stageQuestionnaireIds,
            'prompt_order' => $step->promptOrder,
            'prompt_state_id' => $step->promptStateId,
            'scored' => $step->scored,
            'generate_tiers' => $step->generateTiers,
            'session_id' => $step->sessionId,
            'answers' => $command->answers,
        ], $step->customerId);
    }
}
