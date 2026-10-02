<?php

namespace App\Tests\Functional\Api\Assignations;

use App\Tests\Support\ApiTestCase;

/**
 * PRD §7.11 and §8.8 PUT /assignations/{id}/reviews/{question_id}: only follow-ups, only once complete, not on locked
 * questions or message slides; the review state that follows; and the detail's answers table for the owner.
 */
final class ReviewTest extends ApiTestCase
{
    use AssignationFixtures;

    private string $owner;
    private string $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock()->set('2026-09-30T12:00:00Z');
        $this->owner = $this->account('ACME0001');
        [$this->org] = $this->organizationWith('ACME0001', 'Acme', [['ana', 'ana@acme.test'], ['luis', 'luis@acme.test']]);
    }

    public function testTheOwnerApprovesAndRejectsAnswersOfACompleteFollowUp(): void
    {
        $id = $this->completeFollowUp();
        $url = '/api/v1/assignations/'.$id.'/reviews/';

        $first = $this->data($this->api('PUT', $url.'q1', ['status' => 'approved', 'comment' => '  '], as: $this->owner));
        self::assertSame('q1', $first['question_id']);
        self::assertSame('approved', $first['review']['status']);
        self::assertNull($first['review']['comment'], 'PRD §8.8: an empty comment becomes null');
        self::assertSame(1, $first['review']['attempt'], 'PRD §7.11: a review counts for the attempt it was made in');
        self::assertSame('in_review', $first['review_status'], 'one answer is still not reviewed');

        $second = $this->data($this->api('PUT', $url.'q2', ['status' => 'rejected', 'comment' => 'Add the photos'], as: $this->owner));
        self::assertSame('Add the photos', $second['review']['comment']);
        self::assertSame('changes_requested', $second['review_status'], 'every answer reviewed, one rejected');

        $changed = $this->data($this->api('PUT', $url.'q2', ['status' => 'approved'], as: $this->owner));
        self::assertSame('approved', $changed['review_status'], 'a decision can change; every answer approved');
    }

    public function testTheDetailShowsEachAnswerWithItsReviewToTheOwnerOnly(): void
    {
        $id = $this->completeFollowUp(withMessage: true);
        $this->data($this->api('PUT', '/api/v1/assignations/'.$id.'/reviews/q2', ['status' => 'rejected', 'comment' => 'Too short'], as: $this->owner));

        $detail = $this->data($this->api('GET', '/api/v1/assignations/'.$id, as: $this->owner));

        self::assertTrue($detail['completed']);
        self::assertSame('in_review', $detail['review_status']);
        $answers = $detail['attempts'][0]['answers'];
        self::assertSame(['q1', 'q2'], array_column($answers, 'question_id'), 'message slides are not in the table');
        self::assertSame([1, 2], array_column($answers, 'position'));
        self::assertSame('Answer to q1', $answers[0]['answer']);
        self::assertSame('not_reviewed', $answers[0]['review_state']);
        self::assertSame('rejected', $answers[1]['review_state']);
        self::assertSame('Too short', $answers[1]['review']['comment']);
        self::assertNull($this->data($this->api('GET', '/api/v1/assignations/'.$id))['attempts'][0]['answers'], 'the respondent page never sees the answers');
    }

    public function testTheDetailShowsATablesRowsAndTheLabelsOfTheChosenOptions(): void
    {
        $id = $this->completeFollowUp();
        $session = $this->sharedSession($id);
        $questions = $session->questions();
        $at = '2026-09-30T12:00:00.000Z';
        $questions[0]['options'] = [[
            'name' => 'who', 'type' => 'table', 'rows' => ['Altas', 'Bajas'], 'timestamp' => $at,
            'options' => [['label' => 'Nombre', 'value' => 'nombre'], ['label' => 'Correo', 'value' => 'correo']],
            'value' => [['nombre' => 'Ana', 'correo' => 'ana@acme.test'], ['nombre' => 'Luis']],
        ]];
        $questions[1]['options'] = [[
            'name' => 'how', 'type' => 'checkbox', 'timestamp' => $at,
            'options' => [['label' => 'Se usará nombre', 'value' => 'se-usara-nombre'], ['label' => 'Clave', 'value' => 'clave']],
            'value' => ['se-usara-nombre', 'clave'],
        ]];
        $session->answer($questions, new \DateTimeImmutable($at));
        $this->em()->flush();

        $answers = $this->data($this->api('GET', '/api/v1/assignations/'.$id, as: $this->owner))['attempts'][0]['answers'];

        self::assertSame("Altas — Nombre: Ana; Correo: ana@acme.test\nBajas — Nombre: Luis", $answers[0]['answer'], 'PRD §10.11: a table answer shows its rows, not "not answered"');
        self::assertSame('Se usará nombre, Clave', $answers[1]['answer'], 'PRD §10.11: a choice shows the option labels, not their values');
    }

    public function testOnlyACompleteFollowUpIsReviewed(): void
    {
        $default = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['type' => 'default']);
        $open = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'));
        $this->answerAs($open, 'ana@acme.test', ['q1' => 'Yes'], submit: false);

        $this->assertApiError($this->review($default, 'q1', 'approved'), 400, 'NOT_A_FOLLOW_UP', 'PRD §8.8');
        $this->assertApiError($this->review($open, 'q1', 'approved'), 409, 'FOLLOW_UP_NOT_COMPLETED', 'PRD §8.8: only once complete');
    }

    public function testUnknownQuestionsMessageSlidesAndLockedAnswersAreRefused(): void
    {
        $id = $this->completeFollowUp(withMessage: true);
        $session = $this->sharedSession($id);
        $questions = $session->questions();
        foreach ($questions as $i => $question) {
            if ('q1' === $question['id']) {
                $questions[$i]['options'][0]['locked'] = true;
            }
        }
        $session->answer($questions, new \DateTimeImmutable('2026-09-30T12:00:00Z'));
        $this->em()->flush();

        $this->assertApiError($this->review($id, 'nope', 'approved'), 404, 'QUESTION_NOT_FOUND');
        $this->assertApiError($this->review($id, 'intro', 'approved'), 404, 'QUESTION_NOT_FOUND', 'PRD §8.8: not on message slides');
        $this->assertApiError($this->review($id, 'q1', 'rejected'), 400, 'QUESTION_LOCKED', 'PRD §8.8: not on locked questions');
        $this->assertApiError($this->review($id, 'q2', 'maybe'), 400, 'VALIDATION_ERROR', 'status: approved | rejected');
        $this->assertApiError($this->api('PUT', '/api/v1/assignations/'.$id.'/reviews/q2', ['status' => 'approved', 'comment' => str_repeat('a', 1001)], as: $this->owner), 400, 'VALIDATION_ERROR', 'comment ≤ 1000');
    }

    public function testAnotherAccountGets404AndAReaderCannotReview(): void
    {
        $id = $this->completeFollowUp();
        $this->account('GLOBEX01');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);

        $this->assertApiError($this->api('PUT', '/api/v1/assignations/'.$id.'/reviews/q1', ['status' => 'approved'], as: 'root@globex01.test'), 404, 'ASSIGNATION_NOT_FOUND', "PRD §8.8: another account's returns 404");
        $this->assertApiError($this->api('PUT', '/api/v1/assignations/'.$id.'/reviews/q1', ['status' => 'approved'], as: 'reader@acme.test'), 403, 'FORBIDDEN');
    }

    /** @return array{status: int, json: mixed, body: string} */
    private function review(string $id, string $questionId, string $status): array
    {
        return $this->api('PUT', '/api/v1/assignations/'.$id.'/reviews/'.$questionId, ['status' => $status], as: $this->owner);
    }

    private function completeFollowUp(bool $withMessage = false): string
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001', 2, $withMessage));
        $this->answerAs($id, 'ana@acme.test');

        return $id;
    }
}
