<?php

namespace App\Generation\Application\Command;

use App\Generation\Application\GenerationSettings;
use App\Generation\Application\Job\LinkedinQuestionnaireJob;
use App\Jobs\Application\Jobs;
use App\Shared\Domain\Error\Unavailable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RequestLinkedinQuestionnaireHandler
{
    public function __construct(
        private readonly GenerationSettings $settings,
        private readonly Jobs $jobs,
    ) {
    }

    public function __invoke(RequestLinkedinQuestionnaire $command): string
    {
        $owner = $this->settings->linkedinOwnerCustomerId();
        if (null === $owner) {
            throw new Unavailable('LINKEDIN_UNAVAILABLE', 'Generation from LinkedIn is not configured.');
        }

        return $this->jobs->start(LinkedinQuestionnaireJob::TYPE, [
            'linkedin_url' => $command->linkedinUrl,
            'language' => $command->language,
        ], $owner);
    }
}
