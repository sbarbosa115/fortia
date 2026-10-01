<?php

namespace App\Questionnaires\Application\Command;

use App\Billing\Application\Features;
use App\Questionnaires\Domain\Error\QuestionnaireAlreadyAnswered;
use App\Questionnaires\Domain\Error\QuestionnaireNotFound;
use App\Questionnaires\Domain\Error\SlugAlreadyInUse;
use App\Questionnaires\Domain\Event\QuestionnaireCreated;
use App\Questionnaires\Domain\Flow\FlowDraft;
use App\Questionnaires\Domain\Flow\Slugs;
use App\Questionnaires\Domain\Model\Diagnostic;
use App\Questionnaires\Domain\Model\Flow;
use App\Questionnaires\Domain\Model\Prompt;
use App\Questionnaires\Domain\Model\Questionnaire;
use App\Questionnaires\Domain\Repository\DiagnosticRepository;
use App\Questionnaires\Domain\Repository\FlowRepository;
use App\Questionnaires\Domain\Repository\PromptRepository;
use App\Questionnaires\Domain\Repository\QuestionnaireRepository;
use App\Responses\Application\Query\SessionQueries;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Storage\ObjectStorage;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Document\FileTemplate;
use App\Shared\Domain\Ids;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class SaveFlowHandler
{
    public function __construct(
        private readonly QuestionnaireRepository $questionnaires,
        private readonly FlowRepository $flows,
        private readonly DiagnosticRepository $diagnostics,
        private readonly PromptRepository $prompts,
        private readonly SessionQueries $sessions,
        private readonly ObjectStorage $storage,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SaveFlow $command): string
    {
        $draft = FlowDraft::parse($command->states, $command->slug, $command->cta, $command->layout, $command->resultCopy);
        $draft->assertPromptKeysBelongTo($command->customerId);
        $draft->assertTemplateKeysBelongTo($command->customerId);

        return null === $command->questionnaireId ? $this->create($command, $draft) : $this->update($command, $command->questionnaireId, $draft);
    }

    private function create(SaveFlow $command, FlowDraft $draft): string
    {
        $now = $this->clock->now();
        $slug = $this->slugFor($draft, null);
        $id = Ids::uuid4();

        $questionnaire = new Questionnaire($id, $command->customerId, $draft->title(), $draft->type, $this->questions($draft, $command->customerId), $now);
        $questionnaire->describe(self::fields($draft), $now);
        $questionnaire->syncFlowCopies($slug, $draft->isChain());
        $this->questionnaires->add($questionnaire);

        $flow = new Flow(Ids::alphanumeric(20), $slug, $command->customerId, $id, [], $now);
        $this->flows->add($flow);
        $this->define($flow, $command, $draft, $id, $slug, $now);

        $feature = $command->feature ?? Features::forQuestionnaireType($draft->usageType());
        $this->events->publish($command->countsUsage
            ? QuestionnaireCreated::of($command->customerId, $id, $feature, $command->source)
            : new QuestionnaireCreated($command->customerId, null, ['questionnaire_id' => $id, 'source' => $command->source]));

        return $id;
    }

    private function update(SaveFlow $command, string $id, FlowDraft $draft): string
    {
        $questionnaire = $this->questionnaires->get($id);
        if ($questionnaire->customerId() !== $command->customerId) {
            throw new QuestionnaireNotFound();
        }
        if ($this->sessions->hasAnyResponse($id)) {
            throw new QuestionnaireAlreadyAnswered();
        }

        $now = $this->clock->now();
        $flow = $this->flows->findByQuestionnaire($id);
        $slug = $this->slugFor($draft, $flow);

        $questionnaire->describe(self::fields($draft), $now);
        $questionnaire->replaceQuestions($this->questions($draft, $command->customerId), $now);
        $questionnaire->syncFlowCopies($slug, $draft->isChain());

        if (null === $flow) {
            $flow = new Flow(Ids::alphanumeric(20), $slug, $command->customerId, $id, [], $now);
            $this->flows->add($flow);
        }
        $this->define($flow, $command, $draft, $id, $slug, $now);

        return $id;
    }

    /** Stores the diagnostic and the prompts, and points the flow's states at them. */
    private function define(Flow $flow, SaveFlow $command, FlowDraft $draft, string $questionnaireId, string $slug, \DateTimeImmutable $now): void
    {
        $diagnosticId = $this->saveDiagnostic($questionnaireId, $draft);
        $prompts = $this->savePrompts($questionnaireId, $command->customerId, $draft);

        $flow->redefine($slug, $draft->storedStates($questionnaireId, $diagnosticId, $prompts), $draft->cta, $draft->layout, $draft->resultCopy, $now);
        if (null !== $command->detail) {
            $flow->setDetail($command->detail);
        }
        if (null !== $command->sourceUrl) {
            $flow->setSourceUrl('' === trim($command->sourceUrl) ? null : trim($command->sourceUrl));
        }
    }

    private function saveDiagnostic(string $questionnaireId, FlowDraft $draft): ?string
    {
        $existing = $this->diagnostics->findByQuestionnaire($questionnaireId);
        if (null === $draft->diagnostic) {
            if (null !== $existing) {
                $this->diagnostics->remove($existing);
            }

            return null;
        }
        if (null !== $existing) {
            $existing->rebuild($draft->diagnostic['tiers'], $draft->diagnostic['recommendations'], $draft->diagnostic['action_plan']);

            return $existing->id();
        }
        $diagnostic = new Diagnostic(Ids::uuid4(), $questionnaireId, $draft->diagnostic['tiers'], $draft->diagnostic['recommendations'], $draft->diagnostic['action_plan']);
        $this->diagnostics->add($diagnostic);

        return $diagnostic->id();
    }

    /**
     * The prompts are rebuilt on every save (PRD §7.5); a text sent inline is uploaded to object storage first.
     *
     * @return array<string, array{key: string, prompt_id: string}> by state id
     */
    private function savePrompts(string $questionnaireId, string $customerId, FlowDraft $draft): array
    {
        foreach ($this->prompts->listByQuestionnaire($questionnaireId) as $old) {
            $this->prompts->remove($old);
        }

        $saved = [];
        foreach ($draft->prompts as $prompt) {
            $key = $prompt['key'];
            if (null !== $prompt['text']) {
                $key = 'prompts/'.$customerId.'/'.Ids::uuid4().'.txt';
                $this->storage->put($key, $prompt['text'], 'text/plain; charset=utf-8');
            }
            $entity = new Prompt(Ids::uuid4(), $questionnaireId, $customerId, (string) $key, $prompt['outcome'], $prompt['order']);
            $this->prompts->add($entity);
            $saved[$prompt['state_id']] = ['key' => (string) $key, 'prompt_id' => $entity->id()];
        }

        return $saved;
    }

    /**
     * The questions to store: a template sent as text is uploaded to templates/{customer_id}/{uuid}/{filename}.
     *
     * @return list<array<string, mixed>>
     */
    private function questions(FlowDraft $draft, string $customerId): array
    {
        return $draft->questionsWithStoredTemplates(function (string $filename, string $text) use ($customerId): string {
            $key = FileTemplate::keyFor($customerId, Ids::uuid4(), $filename);
            $this->storage->put($key, $text, 'text/csv; charset=utf-8');

            return $key;
        });
    }

    private function slugFor(FlowDraft $draft, ?Flow $flow): string
    {
        $exceptFlowId = $flow?->id();
        if (null !== $draft->slug) {
            if ($this->flows->slugExists($draft->slug, $exceptFlowId)) {
                throw new SlugAlreadyInUse();
            }

            return $draft->slug;
        }
        if (null !== $flow) {
            return $flow->slug();
        }

        return Slugs::fromTitle($draft->title(), fn (string $slug): bool => $this->flows->slugExists($slug, $exceptFlowId));
    }

    /**
     * The questionnaire's fields as a save replaces them.
     *
     * @return array<string, mixed>
     */
    private static function fields(FlowDraft $draft): array
    {
        return [
            'title' => $draft->title(),
            'description' => $draft->fields['description'] ?? null,
            'disclaimer' => $draft->fields['disclaimer'] ?? null,
            'capture_user_data' => $draft->fields['capture_user_data'] ?? false,
            'landing_page' => $draft->fields['landing_page'] ?? false,
            'type' => $draft->type,
            'on_completed' => $draft->onCompleted,
        ];
    }
}
