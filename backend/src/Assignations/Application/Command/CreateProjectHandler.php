<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Domain\Event\AssignationCreated;
use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\Model\Project;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Assignations\Domain\Repository\ProjectRepository;
use App\Organizations\Application\Query\OrganizationQueries;
use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Ids;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateProjectHandler
{
    /** How many times a follow-up may be sent back for correction (the console's wizard default). */
    public const MAX_FOLLOW_UPS = 2;

    public const REGISTRATION_TITLE = 'Tell us who you are';

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ProjectAssignationSet $assignations,
        private readonly AssignationRepository $assignationRepository,
        private readonly AssignationConfiguration $configuration,
        private readonly OrganizationQueries $organizations,
        private readonly QuestionnaireQueries $questionnaires,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CreateProject $command): string
    {
        $organization = $this->organizations->find($command->organizationId);
        if (null === $organization || !$command->caller->owns((string) $organization['customer_id'])) {
            throw new NotFound('ORGANIZATION_NOT_FOUND', 'Organization not found.');
        }
        $assignations = $this->assignations->resolve($command->caller, $command->organizationId, null, $command->assignationIds);

        $now = $this->clock->now();
        $id = Ids::uuid4();
        $customerId = (string) $organization['customer_id'];
        // Every questionnaire is checked before anything is added, so a refusal leaves nothing behind.
        $created = [];
        foreach (array_values(array_unique(array_map('strtolower', $command->questionnaireIds))) as $questionnaireId) {
            $created[] = $this->followUp($command, $customerId, $questionnaireId, $now);
        }

        // The project belongs to its organization's account (an Admin may create one for any account).
        $project = new Project($id, $customerId, $command->organizationId, trim($command->name), $command->dueDate, $now);
        $project->change(trim($command->name), self::description($command->description), $command->dueDate, $now);
        $this->projects->add($project);
        foreach ($created as $assignation) {
            $this->assignationRepository->add($assignation);
        }
        $this->assignations->replace($id, [...$assignations, ...$created], $now);
        foreach ($created as $assignation) {
            $this->events->publish(AssignationCreated::of($customerId, $assignation->assignationsId()));
        }

        return $id;
    }

    public static function description(?string $description): ?string
    {
        $description = null === $description ? null : trim($description);

        return '' === $description ? null : $description;
    }

    /** A new follow-up of the questionnaire, checked but not added: named after it, for everybody, the default registration. */
    private function followUp(CreateProject $command, string $customerId, string $questionnaireId, \DateTimeImmutable $at): Assignation
    {
        $questionnaire = $this->questionnaires->find($questionnaireId);
        $name = null === $questionnaire ? '' : trim($questionnaire->title());
        $assignation = new Assignation(Ids::uuid4(), $customerId, $command->organizationId, $questionnaireId, $name, Assignation::FOLLOW_UP, $at);
        $this->configuration->apply($command->caller, $assignation, [
            'organization_id' => $command->organizationId,
            'questionnaire_id' => $questionnaireId,
            'name' => '' === $name ? 'Questionnaire' : $name,
            'description' => null,
            'active' => true,
            'audience' => null,
            'max_follow_ups' => self::MAX_FOLLOW_UPS,
            'due_date' => $command->dueDate,
            'questions' => [self::registration($command->registrationTitle)],
        ], $at);

        return $assignation;
    }

    /**
     * The registration slide the console's default builds (PRD §10.11): full name and email, both required.
     *
     * @return array<string, mixed>
     */
    public static function registration(?string $title): array
    {
        $title = null === $title ? '' : trim($title);

        return [
            'id' => 'registration-1',
            'title' => '' === $title ? self::REGISTRATION_TITLE : $title,
            'category' => 'user-capture-data',
            'required' => true,
            'options' => [
                ['name' => 'name', 'type' => 'text', 'options' => [], 'validations' => [['type' => 'required']]],
                ['name' => 'email', 'type' => 'email', 'options' => [], 'validations' => [['type' => 'required']]],
            ],
        ];
    }
}
