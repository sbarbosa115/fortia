<?php

namespace App\Questionnaires\Application\Command;

use App\Identity\Application\Query\AccountQueries;
use App\Questionnaires\Application\Query\QuestionnaireDetails;
use App\Questionnaires\Domain\Event\QuestionnaireCreated;
use App\Questionnaires\Domain\Flow\CopyNaming;
use App\Questionnaires\Domain\Model\Diagnostic;
use App\Questionnaires\Domain\Model\Flow;
use App\Questionnaires\Domain\Model\Prompt;
use App\Questionnaires\Domain\Model\Questionnaire;
use App\Questionnaires\Domain\Repository\DiagnosticRepository;
use App\Questionnaires\Domain\Repository\FlowRepository;
use App\Questionnaires\Domain\Repository\PromptRepository;
use App\Questionnaires\Domain\Repository\QuestionnaireRepository;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Storage\ObjectNotFound;
use App\Shared\Application\Storage\ObjectStorage;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;
use App\Shared\Domain\Text;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CopyQuestionnaireHandler
{
    public function __construct(
        private readonly QuestionnaireRepository $questionnaires,
        private readonly FlowRepository $flows,
        private readonly DiagnosticRepository $diagnostics,
        private readonly PromptRepository $prompts,
        private readonly QuestionnaireDetails $details,
        private readonly AccountQueries $accounts,
        private readonly ObjectStorage $storage,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CopyQuestionnaire $command): string
    {
        $original = $this->questionnaires->get($command->questionnaireId);
        $customerId = $original->customerId();
        $language = (string) ($this->accounts->find($customerId)['language'] ?? 'es-CO');
        $now = $this->clock->now();
        $flow = $this->flows->findByQuestionnaire($original->questionnaireId());

        $title = CopyNaming::title($original->title(), $language, fn (string $t): bool => $this->questionnaires->titleExists($customerId, $t));
        $baseSlug = $flow?->slug() ?? $original->slug() ?? Text::slugify($original->title());
        $slug = CopyNaming::slug('' === $baseSlug ? 'questionnaire' : $baseSlug, $language, fn (string $s): bool => $this->flows->slugExists($s));

        $id = Ids::uuid4();
        $copy = new Questionnaire($id, $customerId, $title, $original->type(), $original->questions(), $now);
        $copy->describe([
            'description' => $original->description(),
            'disclaimer' => $original->disclaimer(),
            'capture_user_data' => $original->captureUserData(),
            'landing_page' => $original->landingPage(),
            'on_completed' => $original->onCompleted(),
        ], $now);
        $this->questionnaires->add($copy);

        $diagnosticId = null;
        $diagnostic = $this->diagnostics->findByQuestionnaire($original->questionnaireId());
        if (null !== $diagnostic) {
            $diagnosticId = Ids::uuid4();
            $this->diagnostics->add(new Diagnostic($diagnosticId, $id, $diagnostic->tiers(), $diagnostic->recommendations(), $diagnostic->actionPlan()));
        }

        $promptsByKey = [];
        foreach ($this->prompts->listByQuestionnaire($original->questionnaireId()) as $prompt) {
            try {
                $text = $this->storage->get($prompt->s3Path());
            } catch (ObjectNotFound) {
                $text = '';
            }
            $key = 'prompts/'.$customerId.'/'.Ids::uuid4().'.txt';
            $this->storage->put($key, $text, 'text/plain; charset=utf-8');
            $copied = new Prompt(Ids::uuid4(), $id, $customerId, $key, $prompt->outcome(), $prompt->order());
            $this->prompts->add($copied);
            $promptsByKey[$prompt->s3Path()] = ['key' => $key, 'prompt_id' => $copied->id()];
        }

        $states = null === $flow
            ? [['state_id' => Ids::alphanumeric(15), 'type' => 'questionnaire', 'parameters' => ['questionnaire_id' => $id], 'outputs' => [], 'next' => null]]
            : self::copiedStates($flow->states(), $id, $diagnosticId, $promptsByKey);
        $copiedFlow = new Flow(Ids::alphanumeric(20), $slug, $customerId, $id, $states, $now);
        $copiedFlow->redefine($slug, $states, $flow?->cta(), $flow?->layout(), $flow?->resultCopy(), $now);
        $copiedFlow->setDetail($flow?->detail() ?? '');
        $this->flows->add($copiedFlow);
        $copy->syncFlowCopies($slug, $copiedFlow->isChain());

        $this->events->publish(QuestionnaireCreated::of($customerId, $id, $this->details->featureOf($original->questionnaireId()), 'copy'));

        return $id;
    }

    /**
     * @param list<array<string, mixed>>                           $states
     * @param array<string, array{key: string, prompt_id: string}> $promptsByKey
     *
     * @return list<array<string, mixed>>
     */
    private static function copiedStates(array $states, string $questionnaireId, ?string $diagnosticId, array $promptsByKey): array
    {
        foreach ($states as $i => $state) {
            $parameters = \is_array($state['parameters'] ?? null) ? $state['parameters'] : [];
            switch ($state['type'] ?? null) {
                case 'questionnaire':
                    $parameters['questionnaire_id'] = $questionnaireId;
                    break;
                case 'diagnostic':
                    unset($parameters['diagnostic_id']);
                    if (null !== $diagnosticId) {
                        $parameters['diagnostic_id'] = $diagnosticId;
                    }
                    break;
                case 'prompt':
                    $key = (string) ($parameters['key'] ?? '');
                    if (isset($promptsByKey[$key])) {
                        $parameters = array_merge($parameters, $promptsByKey[$key]);
                    }
                    break;
            }
            $states[$i]['parameters'] = $parameters;
        }

        return $states;
    }
}
