<?php

namespace App\Tests\Functional\Api\Reporting;

use App\Organizations\Domain\Model\Organization;
use App\Organizations\Domain\Model\OrganizationUser;
use App\Questionnaires\Domain\Model\Flow;
use App\Questionnaires\Domain\Model\Questionnaire;
use App\Responses\Domain\Model\QuestionnaireSession;
use App\Responses\Domain\Model\SessionResults;
use App\Shared\Domain\Ids;

/**
 * Questionnaires, sessions and results built straight from the entities (the authoring and sessions APIs are other
 * items of the split), for the Reporting tests.
 */
trait ReportingFixtures
{
    /** @return list<array<string, mixed>> */
    protected static function surveyQuestions(): array
    {
        return [
            ['id' => 'q-intro', 'order' => 0, 'title' => 'Welcome', 'options' => [['name' => 'c0', 'type' => 'message']]],
            ['id' => 'q-score', 'order' => 1, 'title' => 'How likely are you to recommend us?', 'options' => [['name' => 'c1', 'type' => 'range', 'validations' => [['type' => 'min', 'value' => 0], ['type' => 'max', 'value' => 10]]]]],
            ['id' => 'q-channel', 'order' => 2, 'title' => 'Where did you buy?', 'options' => [['name' => 'c2', 'type' => 'radio', 'options' => [['label' => 'Web', 'value' => 'web'], ['label' => 'Store', 'value' => 'store']]]]],
            ['id' => 'q-comment', 'order' => 3, 'title' => 'Anything else?', 'required' => false, 'options' => [['name' => 'c3', 'type' => 'text']]],
        ];
    }

    /** @param list<array<string, mixed>>|null $questions */
    protected function questionnaire(string $customerId, string $title = 'Customer survey', ?array $questions = null, string $type = 'default', ?string $parent = null, ?string $originSessionId = null): string
    {
        $id = Ids::uuid4();
        $questionnaire = new Questionnaire($id, $customerId, $title, $type, $questions ?? self::surveyQuestions(), $this->clock()->now());
        if (null !== $parent) {
            $questionnaire->makeStageOf($parent, $originSessionId);
        }
        $this->em()->persist($questionnaire);
        $this->em()->flush();

        return $id;
    }

    protected function flow(string $customerId, string $questionnaireId, string $slug): string
    {
        $id = Ids::alphanumeric(20);
        $this->em()->persist(new Flow($id, $slug, $customerId, $questionnaireId, [['type' => 'questionnaire']], $this->clock()->now()));
        $this->em()->flush();

        return $id;
    }

    /**
     * A session with values per question id (a string, or a list for checkboxes).
     *
     * @param array<string, string|list<string>>                        $values
     * @param array{name?: string, email?: string, phone?: string}|null $userData
     * @param list<array<string, mixed>>|null                           $questions
     */
    protected function session(string $customerId, string $questionnaireId, array $values = [], string $status = 'completed', ?string $startedAt = null, ?array $userData = null, ?string $organizationUserId = null, ?array $questions = null, ?string $assignationsId = null): string
    {
        $id = Ids::uuid4();
        $started = new \DateTimeImmutable($startedAt ?? '2026-09-01T10:00:00Z');
        $document = ['title' => 'Customer survey', 'type' => 'default', 'parent' => 'ROOT', 'questions' => []];
        foreach ($questions ?? self::surveyQuestions() as $question) {
            if (\array_key_exists($question['id'], $values)) {
                $question['options'][0]['value'] = $values[$question['id']];
                $question['options'][0]['timestamp'] = $started->modify('+30 seconds')->format('Y-m-d\TH:i:s\Z');
            }
            $document['questions'][] = $question;
        }
        $session = new QuestionnaireSession($id, $questionnaireId, $customerId, $document, $started);
        if (null !== $organizationUserId) {
            $session->bindToAssignation($assignationsId ?? Ids::uuid4(), $organizationUserId, null, 1);
        }
        if ('filling' !== $status) {
            $session->fillOut($userData, $started->modify('+2 minutes'));
        }
        if ('processing' === $status) {
            $session->markProcessing($started->modify('+2 minutes'));
        } elseif ('completed' === $status) {
            $session->complete($started->modify('+2 minutes'));
        }
        $this->em()->persist($session);
        $this->em()->flush();

        return $id;
    }

    /** @param array<string, mixed> $diagnostic */
    protected function diagnosticResult(string $customerId, string $questionnaireId, string $sessionId, array $diagnostic): void
    {
        $results = new SessionResults($sessionId, $customerId, $questionnaireId, $this->clock()->now());
        $results->recordDiagnostic($diagnostic);
        $this->em()->persist($results);
        $this->em()->flush();
    }

    protected function member(string $customerId, string $name, string $email, ?string $phone = null): string
    {
        $organizationId = Ids::uuid4();
        $this->em()->persist(new Organization($organizationId, $customerId, 'Org of '.$name, $this->clock()->now()));
        $memberId = Ids::uuid4();
        $this->em()->persist(new OrganizationUser($memberId, $organizationId, $name, $email, $phone, 'Analyst', 'Sales', $this->clock()->now()));
        $this->em()->flush();

        return $memberId;
    }
}
