<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Domain\Model\Assignation;
use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Ids;

/**
 * The follow-ups a project gets from questionnaires (the console's assignation, PRD §8.9): one per questionnaire,
 * named after it, for everybody in the organization, the default registration and the project's due date, checked
 * like POST /assignations (404 QUESTIONNAIRE_NOT_FOUND). Built and checked, not added: the caller adds them once all
 * of them pass, so a refusal leaves nothing behind.
 */
final class ProjectFollowUps
{
    /** How many times a follow-up may be sent back for correction (the console's wizard default). */
    public const MAX_FOLLOW_UPS = 2;

    public const REGISTRATION_TITLE = 'Tell us who you are';

    public function __construct(
        private readonly AssignationConfiguration $configuration,
        private readonly QuestionnaireQueries $questionnaires,
    ) {
    }

    /**
     * @param list<string> $questionnaireIds deduplicated here
     *
     * @return list<Assignation>
     */
    public function build(Caller $caller, string $customerId, string $organizationId, array $questionnaireIds, string $dueDate, ?string $registrationTitle, \DateTimeImmutable $at): array
    {
        $built = [];
        foreach (array_values(array_unique(array_map('strtolower', $questionnaireIds))) as $questionnaireId) {
            $questionnaire = $this->questionnaires->find($questionnaireId);
            $name = null === $questionnaire ? '' : trim($questionnaire->title());
            $assignation = new Assignation(Ids::uuid4(), $customerId, $organizationId, $questionnaireId, $name, Assignation::FOLLOW_UP, $at);
            $this->configuration->apply($caller, $assignation, [
                'organization_id' => $organizationId,
                'questionnaire_id' => $questionnaireId,
                'name' => '' === $name ? 'Questionnaire' : $name,
                'description' => null,
                'active' => true,
                'audience' => null,
                'max_follow_ups' => self::MAX_FOLLOW_UPS,
                'due_date' => $dueDate,
                'questions' => [self::registration($registrationTitle)],
            ], $at);
            $built[] = $assignation;
        }

        return $built;
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
