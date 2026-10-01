<?php

namespace App\Tests\Functional\Api\Assignations;

use App\Assignations\Domain\Model\Assignation;
use App\Organizations\Domain\Model\Organization;
use App\Organizations\Domain\Model\OrganizationUser;
use App\Responses\Domain\Model\QuestionnaireSession;
use App\Shared\Domain\Ids;
use App\Tests\Functional\Api\Responses\SessionFixtures;
use App\Tests\Support\SwitchableMailer;

/**
 * Organizations with members, questionnaires and assignations for the assignation tests, built straight with their
 * entities (or through the API where the test is about it), and the respondent's steps: log in, answer, submit.
 */
trait AssignationFixtures
{
    use SessionFixtures;

    /**
     * An organization and its members: [name, email?, phone?, role?, area?] each. Returns [organization id, member ids
     * by name].
     *
     * @param list<array{0: string, 1?: string|null, 2?: string|null, 3?: string|null, 4?: string|null}> $members
     *
     * @return array{0: string, 1: array<string, string>}
     */
    protected function organizationWith(string $customerId, string $name, array $members = []): array
    {
        $id = Ids::uuid4();
        $at = new \DateTimeImmutable('2026-09-01T00:00:00Z');
        $this->em()->persist(new Organization($id, $customerId, $name, $at));
        $ids = [];
        foreach ($members as $member) {
            $memberId = Ids::uuid4();
            $ids[$member[0]] = $memberId;
            $this->em()->persist(new OrganizationUser($memberId, $id, $member[0], $member[1] ?? null, $member[2] ?? null, $member[3] ?? null, $member[4] ?? null, $at));
        }
        $this->em()->flush();

        return [$id, $ids];
    }

    /** @return array<string, mixed> the registration slide the console builds (PRD §10.11: name and email, required) */
    protected static function registration(): array
    {
        return [
            'title' => 'Sign in',
            'category' => 'user-capture-data',
            'options' => [
                ['name' => 'name', 'type' => 'text', 'validations' => [['type' => 'required']]],
                ['name' => 'email', 'type' => 'email', 'validations' => [['type' => 'required']]],
            ],
        ];
    }

    /**
     * POST /assignations as $as with the given fields over a valid follow-up body; returns the new id.
     *
     * @param array<string, mixed> $fields
     */
    protected function createAssignation(string $as, string $organizationId, string $questionnaireId, array $fields = []): string
    {
        $data = $this->data($this->api('POST', '/api/v1/assignations', [
            'organization_id' => $organizationId,
            'questionnaire_id' => $questionnaireId,
            'name' => 'Weekly store check',
            'max_follow_ups' => 2,
            'type' => 'follow_up',
            'questions' => [self::registration()],
            ...$fields,
        ], as: $as), 201);

        return (string) $data['assignation_id'];
    }

    /** A questionnaire of $count text questions q1…qN (a message slide first when $withMessage). */
    protected function questionnaireOf(string $customerId, int $count = 2, bool $withMessage = false): string
    {
        $questions = $withMessage ? [[
            'id' => 'intro', 'order' => 0, 'title' => 'Welcome', 'required' => false,
            'options' => [['name' => 'intro-c', 'type' => 'message', 'options' => [], 'validations' => []]],
        ]] : [];
        for ($i = 1; $i <= $count; ++$i) {
            $questions[] = self::textQuestion("q$i", "Question $i");
        }

        return $this->questionnaire($customerId, questions: $questions);
    }

    /**
     * The respondent login.
     *
     * @param array<string, mixed> $body
     *
     * @return array{status: int, json: mixed, body: string}
     */
    protected function login(string $assignationId, array $body): array
    {
        return $this->api('POST', '/api/v1/assignations/'.$assignationId.'/sessions', ['name' => 'Someone', ...$body]);
    }

    /**
     * Logs in and submits the session with every question answered (or with $values).
     *
     * @param array<string, string> $values
     *
     * @return array<string, mixed> the login's JSON
     */
    protected function answerAs(string $assignationId, string $email, array $values = [], bool $submit = true): array
    {
        $login = $this->login($assignationId, ['email' => $email]);
        self::assertSame(200, $login['status'], $login['body']);
        $session = $login['json']['questionnaire'];
        if ([] === $values) {
            foreach ($session['questions'] as $question) {
                $values[$question['id']] = 'Answer to '.$question['id'];
            }
        }
        $body = ['session_id' => $session['session_id'], 'questions' => self::answered($session, $values)['questions']];
        $response = $this->api($submit ? 'POST' : 'PUT', '/api/v1/questionnaire/session', $body, headers: ['Authorization' => 'Bearer '.$login['json']['token']]);
        self::assertSame(200, $response['status'], $response['body']);

        return $login['json'];
    }

    /**
     * Writes reviews straight on a follow-up's shared session: question id → approved | rejected (attempt of the
     * session).
     *
     * @param array<string, string> $reviews
     */
    protected function reviewDirectly(string $assignationId, array $reviews): void
    {
        $session = $this->sharedSession($assignationId);
        $questions = $session->questions();
        foreach ($questions as $i => $question) {
            if (isset($reviews[$question['id']])) {
                $questions[$i]['review'] = ['status' => $reviews[$question['id']], 'comment' => null, 'reviewed_at' => '2026-09-30T10:00:00Z', 'attempt' => $session->attempt()];
            }
        }
        $session->answer($questions, new \DateTimeImmutable('2026-09-30T10:00:00Z'));
        $this->em()->flush();
    }

    protected function sharedSession(string $assignationId): QuestionnaireSession
    {
        $assignation = $this->storedAssignation($assignationId);
        self::assertNotNull($assignation->sharedSessionId(), 'the follow-up has a shared session');

        return $this->storedSession((string) $assignation->sharedSessionId());
    }

    protected function storedAssignation(string $id): Assignation
    {
        $this->em()->clear();
        $assignation = $this->em()->find(Assignation::class, $id);
        self::assertNotNull($assignation);

        return $assignation;
    }

    protected function mailer(): SwitchableMailer
    {
        return static::getContainer()->get(SwitchableMailer::class);
    }
}
