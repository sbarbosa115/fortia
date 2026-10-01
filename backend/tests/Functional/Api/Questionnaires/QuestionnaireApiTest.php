<?php

namespace App\Tests\Functional\Api\Questionnaires;

use App\Responses\Domain\Model\QuestionnaireSession;
use App\Shared\Application\Storage\ObjectStorage;
use App\Shared\Domain\Ids;
use App\Tests\Support\ApiTestCase;

/** PRD §8.4 POST/PUT /questionnaire, GET/PATCH /questionnaire/{id}, GET /questionnaire/{id}/prompts; §7.5. */
final class QuestionnaireApiTest extends ApiTestCase
{
    use FlowPayloads;

    public function testTheOwnerCreatesARegularQuestionnaireFromAFlow(): void
    {
        $owner = $this->account('ACME0001');

        $id = $this->createQuestionnaire($owner, self::regularFlow('Customer survey', 'customer-survey'));

        $questionnaire = $this->data($this->api('GET', "/api/v1/questionnaire/$id", as: $owner));
        self::assertSame('Customer survey', $questionnaire['title']);
        self::assertSame('ACME0001', $questionnaire['customer_id']);
        self::assertSame('default', $questionnaire['type']);
        self::assertSame('ROOT', $questionnaire['parent']);
        self::assertSame('customer-survey', $questionnaire['slug'], 'the slug is copied onto the questionnaire for listings');
        self::assertTrue($questionnaire['landing_page']);
        self::assertSame(1, $questionnaire['question_count']);
        self::assertTrue(Ids::isUuid4($questionnaire['questions'][0]['id']), 'a question without an id gets a UUID');

        $flow = $this->api('GET', '/api/v1/flow/customer-survey');
        self::assertSame(200, $flow['status']);
        self::assertSame(['questionnaire_id' => $id], $flow['json']['data']['states'][0]['parameters'], 'the stored state points to the questionnaire');
    }

    public function testAnEmptySlugIsGeneratedFromTheTitle(): void
    {
        $owner = $this->account('ACME0001');

        $first = $this->createQuestionnaire($owner, self::regularFlow('Encuesta de Café'));
        $second = $this->createQuestionnaire($owner, self::regularFlow('Encuesta de Café'));

        self::assertSame('encuesta-de-cafe', $this->data($this->api('GET', "/api/v1/questionnaire/$first", as: $owner))['slug']);
        self::assertSame('encuesta-de-cafe-2', $this->data($this->api('GET', "/api/v1/questionnaire/$second", as: $owner))['slug'], 'slugs are unique across the system');
    }

    public function testASlugInUseIsAConflictOnCreateAndOnEdit(): void
    {
        $owner = $this->account('ACME0001');
        $globex = $this->account('GLOBEX01');
        $this->createQuestionnaire($globex, self::regularFlow('Theirs', 'taken'));
        $mine = $this->createQuestionnaire($owner, self::regularFlow('Mine', 'mine'));

        $this->assertApiError($this->api('POST', '/api/v1/questionnaire', self::regularFlow('Mine too', 'taken'), as: $owner), 409, 'SLUG_ALREADY_IN_USE', 'PRD §6.6: unique across the whole system');
        $this->assertApiError($this->api('PUT', '/api/v1/questionnaire', ['questionnaire_id' => $mine] + self::regularFlow('Mine', 'taken'), as: $owner), 409, 'SLUG_ALREADY_IN_USE');

        $keep = $this->api('PUT', '/api/v1/questionnaire', ['questionnaire_id' => $mine] + self::regularFlow('Mine, renamed', 'mine'), as: $owner);
        self::assertSame(200, $keep['status'], 'keeping its own slug is not a conflict: '.$keep['body']);
        self::assertNull($keep['json']['data']);
    }

    public function testAnInvalidFlowIsAValidationError(): void
    {
        $owner = $this->account('ACME0001');
        $flow = self::regularFlow();
        $flow['states'][] = ['state_id' => 'again', 'type' => 'questionnaire', 'parameters' => ['questionnaire' => ['title' => 'Two']]];

        $response = $this->api('POST', '/api/v1/questionnaire', $flow, as: $owner);

        $this->assertApiError($response, 400, 'VALIDATION_ERROR', 'PRD §7.5: exactly one questionnaire state');
        self::assertStringContainsString('exactly one questionnaire state', $response['json']['error']['message']);
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire', ['states' => 'nope'], as: $owner), 400, 'VALIDATION_ERROR');
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire', ['surprise' => 1] + self::regularFlow(), as: $owner), 400, 'VALIDATION_ERROR', 'no extra fields');
    }

    public function testADiagnosticThatCannotBeScoredIsRefused(): void
    {
        $owner = $this->account('ACME0001');
        $flow = self::diagnosticFlow();
        $flow['states'][0]['parameters']['questionnaire']['on_completed']['tiers'][1]['min'] = 6;

        $response = $this->api('POST', '/api/v1/questionnaire', $flow, as: $owner);

        $this->assertApiError($response, 400, 'VALIDATION_ERROR');
        self::assertStringContainsString('gaps', $response['json']['error']['message'], 'PRD §7.5: tiers are contiguous');
    }

    public function testAReadOnlyUserCannotCreateEditOrToggle(): void
    {
        $owner = $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);
        $id = $this->createQuestionnaire($owner, self::regularFlow());

        $this->assertApiError($this->api('POST', '/api/v1/questionnaire', self::regularFlow(), as: 'reader@acme.test'), 403, 'FORBIDDEN', 'PRD §8.4: AG');
        $this->assertApiError($this->api('PUT', '/api/v1/questionnaire', ['questionnaire_id' => $id] + self::regularFlow(), as: 'reader@acme.test'), 403, 'FORBIDDEN');
        $this->assertApiError($this->api('PATCH', "/api/v1/questionnaire/$id", ['is_active' => false], as: 'reader@acme.test'), 403, 'FORBIDDEN');
        $this->assertApiError($this->api('GET', "/api/v1/questionnaire/$id/prompts", as: 'reader@acme.test'), 403, 'FORBIDDEN');
        self::assertSame(200, $this->api('GET', "/api/v1/questionnaire/$id", as: 'reader@acme.test')['status'], 'but they can read it');
    }

    public function testEditingReplacesTheQuestionnaireRebuildsTheDiagnosticAndKeepsTheFlowId(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->createQuestionnaire($owner, self::diagnosticFlow('Maturity', 'maturity'));
        $flowId = $this->data($this->api('GET', "/api/v1/flow/$id"))['id'];

        $edited = self::diagnosticFlow('Maturity v2', 'maturity-v2');
        $edited['states'][0]['parameters']['questionnaire']['on_completed']['tiers'] = [['id' => 'all', 'name' => 'Everyone', 'min' => 0, 'max' => 10]];
        $edited['states'][0]['parameters']['questionnaire']['on_completed']['recommendations'] = [];
        $edited['states'][0]['parameters']['questionnaire']['on_completed']['action_plan'] = [];
        $this->data($this->api('PUT', '/api/v1/questionnaire', ['questionnaire_id' => $id] + $edited, as: $owner));

        $questionnaire = $this->data($this->api('GET', "/api/v1/questionnaire/$id", as: $owner));
        self::assertSame('Maturity v2', $questionnaire['title']);
        self::assertSame(['all'], array_column($questionnaire['on_completed']['tiers'], 'id'), 'the diagnostic is rebuilt');
        $flow = $this->data($this->api('GET', '/api/v1/flow/maturity-v2'));
        self::assertSame($flowId, $flow['id'], 'PRD §7.5: the flow keeps its id');
        self::assertSame(['title' => 'Your result'], $flow['result_copy']);
        self::assertSame(404, $this->api('GET', '/api/v1/flow/maturity')['status'], 'the old slug is free again');
    }

    public function testAQuestionnaireWithAnyResponseCannotBeEdited(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->createQuestionnaire($owner, self::regularFlow());
        $questions = $this->data($this->api('GET', "/api/v1/questionnaire/$id", as: $owner))['questions'];
        $questions[0]['options'][0]['skipped'] = true;
        $this->em()->persist(new QuestionnaireSession(Ids::uuid4(), $id, 'ACME0001', ['questions' => $questions], new \DateTimeImmutable()));
        $this->em()->flush();

        $this->assertApiError($this->api('PUT', '/api/v1/questionnaire', ['questionnaire_id' => $id] + self::regularFlow('Changed'), as: $owner), 409, 'QUESTIONNAIRE_ALREADY_ANSWERED', 'PRD §7.5: a skip is a response too');
    }

    public function testGettingADiagnosticMergesItsTiersIntoOnCompleted(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->createQuestionnaire($owner, self::diagnosticFlow());

        $questionnaire = $this->data($this->api('GET', "/api/v1/questionnaire/$id", as: $owner));

        self::assertSame('diagnostic', $questionnaire['type']);
        self::assertSame('diagnostic', $questionnaire['on_completed']['type']);
        self::assertSame(['t1', 't2'], array_column($questionnaire['on_completed']['tiers'], 'id'));
        self::assertSame('Start small', $questionnaire['on_completed']['recommendations'][0]['recommendation']);
        $flow = $this->data($this->api('GET', "/api/v1/flow/$id"));
        self::assertArrayNotHasKey('tiers', $flow['states'][1]['parameters'], 'the public flow never shows the scoring');
    }

    public function testPatchSetsIsActiveWithAStrictBoolean(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->createQuestionnaire($owner, self::regularFlow());

        $row = $this->data($this->api('PATCH', "/api/v1/questionnaire/$id", ['is_active' => false], as: $owner));

        self::assertFalse($row['is_active']);
        self::assertSame('inactive', $row['status']);
        self::assertFalse($this->data($this->api('GET', "/api/v1/questionnaire/$id", as: $owner))['is_active']);
        $this->assertApiError($this->api('PATCH', "/api/v1/questionnaire/$id", ['is_active' => 'true'], as: $owner), 400, 'VALIDATION_ERROR', 'a strict bool');
        $this->assertApiError($this->api('PATCH', "/api/v1/questionnaire/$id", ['is_active' => true, 'title' => 'x'], as: $owner), 400, 'VALIDATION_ERROR', 'exactly {is_active}');
        $this->assertApiError($this->api('PATCH', "/api/v1/questionnaire/$id", [], as: $owner), 400, 'VALIDATION_ERROR');
    }

    public function testAChainsPromptsAreStoredInObjectStorageAndListedWithTheirText(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->createQuestionnaire($owner, self::chainFlow('Discovery', 'Ask about their goals.'));

        $prompts = $this->data($this->api('GET', "/api/v1/questionnaire/$id/prompts", as: $owner))['prompts'];

        self::assertCount(1, $prompts);
        self::assertSame('Ask about their goals.', $prompts[0]['text']);
        self::assertSame('result', $prompts[0]['outcome'], 'PRD §7.5: the next terminal state');
        self::assertStringStartsWith('prompts/ACME0001/', $prompts[0]['s3_path']);
        $flow = $this->data($this->api('GET', "/api/v1/flow/$id"));
        self::assertSame($prompts[0]['s3_path'], $flow['states'][1]['parameters']['key'], 'prompt states carry the key');
        self::assertArrayNotHasKey('text', $flow['states'][1]['parameters']);
        self::assertTrue($this->data($this->api('GET', "/api/v1/questionnaire/$id", as: $owner))['is_chain']);
    }

    public function testAPromptKeyMustBeAnUploadOfTheSameAccount(): void
    {
        $owner = $this->account('ACME0001');
        static::getContainer()->get(ObjectStorage::class)->put('prompts/ACME0001/uploaded.txt', 'From the console upload', 'text/plain');
        $flow = self::chainFlow();
        $flow['states'][1]['parameters'] = ['key' => 'prompts/ACME0001/uploaded.txt'];
        $id = $this->createQuestionnaire($owner, $flow);
        self::assertSame('From the console upload', $this->data($this->api('GET', "/api/v1/questionnaire/$id/prompts", as: $owner))['prompts'][0]['text']);

        $flow['states'][1]['parameters'] = ['key' => 'prompts/GLOBEX01/secret.txt'];
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire', $flow, as: $owner), 400, 'VALIDATION_ERROR', 'another account\'s prompt text is not readable');
    }

    public function testAnotherTenantGetsNotFoundOnEveryIdRoute(): void
    {
        $owner = $this->account('ACME0001');
        $globex = $this->account('GLOBEX01');
        $id = $this->createQuestionnaire($owner, self::chainFlow());

        $this->assertApiError($this->api('GET', "/api/v1/questionnaire/$id", as: $globex), 404, 'QUESTIONNAIRE_NOT_FOUND', 'another tenant\'s id is 404, never 403');
        $this->assertApiError($this->api('PUT', '/api/v1/questionnaire', ['questionnaire_id' => $id] + self::regularFlow(), as: $globex), 404, 'QUESTIONNAIRE_NOT_FOUND');
        $this->assertApiError($this->api('PATCH', "/api/v1/questionnaire/$id", ['is_active' => false], as: $globex), 404, 'QUESTIONNAIRE_NOT_FOUND');
        $this->assertApiError($this->api('POST', "/api/v1/questionnaire/$id/copy", as: $globex), 404, 'QUESTIONNAIRE_NOT_FOUND');
        $this->assertApiError($this->api('GET', "/api/v1/questionnaire/$id/prompts", as: $globex), 404, 'QUESTIONNAIRE_NOT_FOUND');
        $this->assertApiError($this->api('GET', '/api/v1/questionnaire/'.Ids::uuid4(), as: $owner), 404, 'QUESTIONNAIRE_NOT_FOUND');
        $this->assertApiError($this->api('GET', '/api/v1/questionnaire/not-a-uuid', as: $owner), 400, 'INVALID_UUID');
    }

    public function testAPlatformAdminReadsAndEditsAnyAccount(): void
    {
        $owner = $this->account('ACME0001');
        $admin = $this->admin();
        $id = $this->createQuestionnaire($owner, self::regularFlow());

        self::assertSame('ACME0001', $this->data($this->api('GET', "/api/v1/questionnaire/$id", as: $admin))['customer_id']);
        $this->data($this->api('PUT', '/api/v1/questionnaire', ['questionnaire_id' => $id] + self::regularFlow('Fixed by support'), as: $admin));

        $questionnaire = $this->data($this->api('GET', "/api/v1/questionnaire/$id", as: $owner));
        self::assertSame('Fixed by support', $questionnaire['title']);
        self::assertSame('ACME0001', $questionnaire['customer_id'], 'it stays in its account');
    }

    public function testSignedOutCallersAreRefused(): void
    {
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire', self::regularFlow()), 401, 'UNAUTHORIZED');
        $this->assertApiError($this->api('GET', '/api/v1/questionnaire'), 401, 'UNAUTHORIZED');
    }
}
