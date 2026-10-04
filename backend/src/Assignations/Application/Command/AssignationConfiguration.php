<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Domain\Audience;
use App\Assignations\Domain\Error\AssignationInProject;
use App\Assignations\Domain\Error\AudienceMemberNotInOrganization;
use App\Assignations\Domain\Error\InvalidAssignation;
use App\Assignations\Domain\Model\Assignation;
use App\Organizations\Application\Query\OrganizationQueries;
use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Document\Questions;
use App\Shared\Domain\Error\NotFound;

/**
 * Checks and applies the editable fields of an assignation (PRD §8.8 POST and PUT, §6.14, §7.11 "Other rules"), for
 * CreateAssignationHandler and UpdateAssignationHandler:
 *
 * - the organization and the questionnaire exist and are the caller's (404 ORGANIZATION_NOT_FOUND /
 *   QUESTIONNAIRE_NOT_FOUND), and both belong to the assignation's account;
 * - a questionnaire can be assigned to any number of organizations (each assignation keeps its own answers);
 * - the organization does not change while the assignation is in a project (400 ASSIGNATION_IN_PROJECT);
 * - changing the organization resets a `members` audience that was not sent again;
 * - the audience is valid and its members belong to the organization (400 AUDIENCE_MEMBER_NOT_IN_ORGANIZATION);
 * - the registration slide has at least one question; only a follow-up has a due date.
 */
final class AssignationConfiguration
{
    public function __construct(
        private readonly OrganizationQueries $organizations,
        private readonly QuestionnaireQueries $questionnaires,
    ) {
    }

    /**
     * The organization a new assignation goes to, owned by the caller.
     *
     * @return array<string, mixed>
     */
    public function organization(Caller $caller, string $organizationId): array
    {
        $organization = $this->organizations->find(strtolower($organizationId));
        if (null === $organization || !$caller->owns((string) $organization['customer_id'])) {
            throw new NotFound('ORGANIZATION_NOT_FOUND', 'Organization not found.');
        }

        return $organization;
    }

    /**
     * Applies $fields (PRD names; on update only the ones sent) to $assignation.
     *
     * @param array<string, mixed> $fields
     */
    public function apply(Caller $caller, Assignation $assignation, array $fields, \DateTimeImmutable $at): void
    {
        $organizationId = strtolower((string) ($fields['organization_id'] ?? $assignation->organizationId()));
        $questionnaireId = strtolower((string) ($fields['questionnaire_id'] ?? $assignation->questionnaireId()));
        $organizationChanged = $organizationId !== $assignation->organizationId();

        if ($organizationChanged && null !== $assignation->projectId()) {
            throw new AssignationInProject();
        }
        $organization = $this->organization($caller, $organizationId);
        if ($organization['customer_id'] !== $assignation->customerId()) {
            throw new NotFound('ORGANIZATION_NOT_FOUND', 'Organization not found.');
        }
        $questionnaire = $this->questionnaires->find($questionnaireId);
        if (null === $questionnaire || $questionnaire->customerId() !== $assignation->customerId()) {
            throw new NotFound('QUESTIONNAIRE_NOT_FOUND', 'The questionnaire does not exist.');
        }
        $audience = \array_key_exists('audience', $fields)
            ? Audience::normalize(\is_array($fields['audience']) ? $fields['audience'] : null)
            : $assignation->audience();
        if ($organizationChanged && !\array_key_exists('audience', $fields) && Audience::MEMBERS === $audience['type']) {
            $audience = Audience::everybody();
        }
        $dueDate = \array_key_exists('due_date', $fields) ? self::nullableString($fields['due_date']) : $assignation->dueDate();
        $questions = \array_key_exists('questions', $fields) ? self::registration((array) $fields['questions']) : $assignation->questions();
        $name = \array_key_exists('name', $fields) ? trim((string) $fields['name']) : $assignation->name();

        $violations = Audience::violations($audience);
        if ('' === $name) {
            $violations[] = ['field' => 'name', 'message' => 'This value should not be blank.'];
        }
        if ([] === $questions) {
            $violations[] = ['field' => 'questions', 'message' => 'The registration needs at least one question.'];
        }
        if (null !== $dueDate && !$assignation->isFollowUp()) {
            $violations[] = ['field' => 'due_date', 'message' => 'Only follow-ups have a due date.'];
        }
        if ([] !== $violations) {
            throw new InvalidAssignation($violations);
        }
        $unknown = Audience::unknownMembers($audience, $this->organizations->membersOf($organizationId));
        if ([] !== $unknown) {
            throw new AudienceMemberNotInOrganization($unknown);
        }

        $assignation->configure(
            $organizationId,
            $questionnaireId,
            $name,
            \array_key_exists('description', $fields) ? self::nullableString($fields['description']) : $assignation->description(),
            \array_key_exists('max_follow_ups', $fields) ? max(0, (int) $fields['max_follow_ups']) : $assignation->maxFollowUps(),
            \array_key_exists('active', $fields) ? (bool) $fields['active'] : $assignation->isActive(),
            $dueDate,
            $audience,
            $questions,
            $at,
        );
    }

    /**
     * The registration slide in the questionnaire document shape (PRD §6.5), each question with an id.
     *
     * @param array<int|string, mixed> $questions
     *
     * @return list<array<string, mixed>>
     */
    private static function registration(array $questions): array
    {
        $list = array_values(array_filter($questions, 'is_array'));
        $normalized = Questions::normalizeAll($list);
        foreach ($normalized as $i => $question) {
            if ('' === $question['id']) {
                $normalized[$i]['id'] = 'registration-'.($i + 1);
            }
        }

        return $normalized;
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = null === $value ? null : trim((string) $value);

        return '' === $value ? null : $value;
    }
}
