<?php

namespace App\Tests\Functional\Api\Responses;

use App\Shared\Application\Llm\LlmResponse;
use App\Shared\Domain\Ids;
use App\Tests\Support\ApiTestCase;

/** The AI evaluation of answers (PRD §7.9, §8.4 POST …/answers/{question_id}/evaluate). */
final class EvaluationTest extends ApiTestCase
{
    use SessionFixtures;

    public function testAnAnswerThatDoesNotMeetItsCriteriaCostsAFollowUpAndComesBackWithAMessage(): void
    {
        $session = $this->sessionWith(self::textQuestion('q1', 'Describe your process', 2, ['Mentions a tool', 'Gives an example']));

        $job = $this->evaluate($session['session_id'], 'q1', 'Fine');

        self::assertSame('COMPLETED', $job['status']);
        self::assertSame('evaluation', $job['result']['type']);
        self::assertSame('not_sense', $job['result']['status'], '§7.9: an unrelated or weak answer does not pass');
        self::assertSame(1, $job['result']['question']['max_followups'], 'max_followups decreases by 1');
        self::assertNotEmpty($job['result']['question']['improvement_message']);
        $stored = $this->storedSession($session['session_id'])->questions()[0];
        self::assertSame(1, $stored['max_followups'], 'the server keeps the count, so a respondent cannot reset it');
        self::assertSame('Fine', $stored['flagged_answer']);
        $request = $this->llm()->requests()[0];
        self::assertSame('followups--rules-to-evaluate-answers', $request->purpose);
        self::assertSame('fast', $request->tier, '§13.3: evaluating answers uses the fast model');
    }

    public function testAnAnswerThatMeetsItsCriteriaPasses(): void
    {
        $session = $this->sessionWith(self::textQuestion('q1', 'Describe your process', 2, ['Mentions a tool']));

        $job = $this->evaluate($session['session_id'], 'q1', 'We use a shared board and review it weekly');

        self::assertSame('success', $job['result']['status']);
        self::assertSame(2, $job['result']['question']['max_followups']);
        self::assertNull($job['result']['question']['improvement_message']);
    }

    public function testTheModelsGradesDecideWithTheAverageOf30(): void
    {
        $session = $this->sessionWith(self::textQuestion('q1', 'Why?', 1, ['a', 'b']));
        $this->llm()->willAnswer(LlmResponse::json(['related' => true, 'grades' => [['criterion' => 'a', 'grade' => 50], ['criterion' => 'b', 'grade' => 9]], 'improvement_message' => 'Say more about b']));

        $job = $this->evaluate($session['session_id'], 'q1', 'Some long enough answer here');

        self::assertSame('not_sense', $job['result']['status'], 'an average of 29.5 does not pass');
        self::assertSame('Say more about b', $job['result']['question']['improvement_message']);
        self::assertSame(0, $job['result']['question']['max_followups']);
    }

    public function testAnswersThatAreNotEvaluatedPassWithoutCallingTheModel(): void
    {
        $session = $this->sessionWith(self::textQuestion('q1', 'Name', 0), self::radioQuestion('q2', null));

        self::assertSame('success', $this->evaluate($session['session_id'], 'q1', 'Ana')['result']['status'], 'no follow-ups allowed');
        self::assertSame('success', $this->evaluate($session['session_id'], 'q2', '1')['result']['status'], 'only text or audio');
        self::assertSame([], $this->llm()->requests());
    }

    public function testWhenTheModelFailsTheJobFailsAndTheRespondentMovesOn(): void
    {
        $session = $this->sessionWith(self::textQuestion('q1', 'Why?', 2));
        $this->llm()->willFail();

        $job = $this->evaluate($session['session_id'], 'q1', 'Because it matters a lot to us');

        self::assertSame('FAILED', $job['status'], '§7.9: the respondent app fails open on a failed evaluation');
        self::assertSame(2, $this->storedSession($session['session_id'])->questions()[0]['max_followups'], 'a failure costs no follow-up');
    }

    public function testAnUnknownSessionOrQuestionIs404(): void
    {
        $session = $this->sessionWith(self::textQuestion('q1', 'Why?', 2));
        $body = ['id' => 'x', 'options' => []];

        $this->assertApiError($this->api('POST', '/api/v1/questionnaire/session/'.Ids::uuid4().'/answers/q1/evaluate', $body), 404, 'SESSION_NOT_FOUND');
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire/session/'.$session['session_id'].'/answers/nope/evaluate', $body), 404, 'QUESTION_NOT_FOUND');
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire/session/'.$session['session_id'].'/answers/q1/evaluate', ['id' => 'q1']), 400, 'VALIDATION_ERROR');
    }

    /**
     * @param array<string, mixed> ...$questions
     *
     * @return array<string, mixed>
     */
    private function sessionWith(array ...$questions): array
    {
        $this->account('ACME0001');

        return $this->startSession($this->questionnaire('ACME0001', questions: array_values($questions)));
    }

    /** @return array<string, mixed> the finished job */
    private function evaluate(string $sessionId, string $questionId, string $answer): array
    {
        $question = ['id' => $questionId, 'options' => [['name' => $questionId.'-c', 'value' => $answer]]];
        $job = $this->data($this->api('POST', '/api/v1/questionnaire/session/'.$sessionId.'/answers/'.$questionId.'/evaluate', $question), 202)['job'];
        self::assertSame('answer_evaluation', $job['job_type']);

        return $this->data($this->api('GET', '/api/v1/jobs/'.$job['job_id']))['job'];
    }
}
