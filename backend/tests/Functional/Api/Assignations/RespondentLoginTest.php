<?php

namespace App\Tests\Functional\Api\Assignations;

use App\Billing\Application\Usage;
use App\Shared\Application\Security\RespondentTokens;
use App\Shared\Domain\Ids;
use App\Tests\Support\ApiTestCase;

/**
 * PRD §7.11 the respondent login (POST /assignations/{id}/sessions), in its order: the assignations feature, a
 * completed follow-up, the identifier, the member lookup (by phone and/or email, never by name), the audience, the
 * responses capacity, then the token and the session. D6/D7: the token expires and an invalid one is 401.
 */
final class RespondentLoginTest extends ApiTestCase
{
    use AssignationFixtures;

    private string $owner;
    private string $org;
    /** @var array<string, string> */
    private array $members;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock()->set('2026-09-30T12:00:00Z');
        $this->owner = $this->account('ACME0001', plan: 'starter');
        [$this->org, $this->members] = $this->organizationWith('ACME0001', 'Acme', [
            ['ana', 'ana@acme.test', '+57 300 111 2233', 'Manager', 'Sales'],
            ['luis', 'luis@acme.test', null, 'Driver', 'Logistics'],
            ['pedro', null, '+573009998877', 'Driver', 'Logistics'],
        ]);
    }

    public function testAMemberSignsInWithTheirEmailAndGetsATokenAndTheirSession(): void
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['type' => 'default']);

        $response = $this->login($id, ['name' => 'Ana', 'email' => 'ANA@acme.test']);

        self::assertSame(200, $response['status'], $response['body']);
        self::assertArrayNotHasKey('data', $response['json'], 'PRD §8.8: bare, without the envelope');
        $token = $response['json']['token'];
        self::assertStringStartsWith('rt.', $token);
        $claims = static::getContainer()->get(RespondentTokens::class)->parse($token);
        self::assertNotNull($claims);
        self::assertSame($id, $claims->assignationsId, '§7.11 step 7: the token binds the assignation');
        self::assertSame($this->members['ana'], $claims->organizationUserId, '…the member');
        $session = $response['json']['questionnaire'];
        self::assertSame($claims->sessionId, $session['session_id'], '…and the session');
        self::assertSame($id, $session['assignations_id']);
        self::assertSame($this->members['ana'], $session['organization_user_id'], 'a default assignation: each member their own session');
        self::assertNull($response['json']['flow']);

        $again = $this->login($id, ['email' => 'ana@acme.test']);
        self::assertSame($session['session_id'], $again['json']['questionnaire']['session_id'], 'signing in again resumes the open session');
        $luis = $this->login($id, ['email' => 'luis@acme.test']);
        self::assertNotSame($session['session_id'], $luis['json']['questionnaire']['session_id']);
    }

    public function testAfterSubmittingAMemberStartsANewAttempt(): void
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['type' => 'default']);
        $first = $this->answerAs($id, 'ana@acme.test');

        $second = $this->login($id, ['email' => 'ana@acme.test'])['json']['questionnaire'];

        self::assertNotSame($first['questionnaire']['session_id'], $second['session_id']);
        self::assertSame(2, $second['attempt']);
    }

    public function testTheLookupIsByPhoneOrEmailNeverByNameAndEveryIdentifierMustMatch(): void
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['type' => 'default']);

        self::assertSame(200, $this->login($id, ['phone' => '+57 (300) 999-8877'])['status'], 'by phone, as digits');
        self::assertSame(200, $this->login($id, ['email' => 'ana@acme.test', 'phone' => '+573001112233'])['status'], 'both of the same member');
        $this->assertApiError($this->login($id, ['name' => 'ana', 'email' => 'someone@acme.test']), 403, 'USER_NOT_FOUND', '§7.11: never by name');
        $this->assertApiError($this->login($id, ['email' => 'ana@acme.test', 'phone' => '+573009998877']), 403, 'USER_NOT_FOUND', '§7.11: every identifier sent must match');
        $this->assertApiError($this->login($id, ['name' => 'ana', 'role' => 'Manager']), 400, 'MISSING_IDENTIFIER', '§7.11 step 3');
        $this->assertApiError($this->login($id, ['email' => '  ', 'phone' => '']), 400, 'MISSING_IDENTIFIER');
    }

    public function testAMemberOutsideTheAudienceIsRefused(): void
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['type' => 'default', 'audience' => ['type' => 'area', 'values' => ['logistics']]]);

        $this->assertApiError($this->login($id, ['email' => 'ana@acme.test']), 403, 'NOT_IN_AUDIENCE', '§7.11 step 5');
        self::assertSame(200, $this->login($id, ['email' => 'luis@acme.test'])['status']);
    }

    public function testTheInputIsValidatedAndAllowsNoExtraFields(): void
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['type' => 'default']);
        $url = '/api/v1/assignations/'.$id.'/sessions';

        $this->assertApiError($this->api('POST', $url, ['email' => 'ana@acme.test']), 400, 'VALIDATION_ERROR', 'PRD §8.8: name is required');
        $this->assertApiError($this->api('POST', $url, ['name' => str_repeat('a', 201), 'email' => 'ana@acme.test']), 400, 'VALIDATION_ERROR', 'name 1–200');
        $this->assertApiError($this->api('POST', $url, ['name' => 'Ana', 'email' => 'ana@acme.test', 'token' => 'x']), 400, 'VALIDATION_ERROR', 'no extra fields');
        $this->assertApiError($this->login(Ids::uuid4(), ['email' => 'ana@acme.test']), 404, 'ASSIGNATION_NOT_FOUND');
        $this->assertApiError($this->login('nope', ['email' => 'ana@acme.test']), 400, 'INVALID_UUID');
    }

    public function testAnInactiveAssignationTakesNoLogins(): void
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['type' => 'default', 'active' => false]);

        $this->assertApiError($this->login($id, ['email' => 'ana@acme.test']), 404, 'ASSIGNATION_NOT_FOUND', 'an inactive assignation is not answered');
    }

    public function testTheAssignationsFeatureIsCheckedFirst(): void
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['type' => 'default']);
        $this->clock()->set('2026-12-01T00:00:00Z');

        $response = $this->login($id, []);

        $this->assertApiError($response, 429, 'PLAN_LIMIT_REACHED', '§7.11 step 1 comes before MISSING_IDENTIFIER');
        self::assertSame('assignations', $response['json']['error']['details']['feature']);
    }

    public function testTheResponsesCapacityIsCheckedAfterTheLookup(): void
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['type' => 'default']);
        static::getContainer()->get(Usage::class)->set('ACME0001', ['responses' => 100]);
        $this->em()->flush();

        $this->assertApiError($this->login($id, ['email' => 'nobody@acme.test']), 403, 'USER_NOT_FOUND', '§7.11: the lookup (step 4–5) comes before the capacity (step 6)');
        $response = $this->login($id, ['email' => 'ana@acme.test']);
        $this->assertApiError($response, 429, 'PLAN_LIMIT_REACHED', '§7.11 step 6: responses capacity');
        self::assertSame('responses', $response['json']['error']['details']['feature']);
    }

    public function testFollowUpMembersShareOneSessionUntilItIsCompleted(): void
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'));

        $ana = $this->login($id, ['email' => 'ana@acme.test'])['json'];
        $luis = $this->login($id, ['email' => 'luis@acme.test'])['json'];

        self::assertSame($ana['questionnaire']['session_id'], $luis['questionnaire']['session_id'], '§7.11: all members write to the same session');
        self::assertNull($ana['questionnaire']['organization_user_id'], 'the shared session belongs to no member');
        self::assertSame('follow_up', $ana['questionnaire']['assignation_type']);
        $stored = $this->storedAssignation($id);
        self::assertSame($ana['questionnaire']['session_id'], $stored->sharedSessionId());
        self::assertSame(1, $stored->attempts()[0]['number'], 'the first login opens attempt 1');

        $this->answerAs($id, 'luis@acme.test');
        $this->assertApiError($this->login($id, []), 409, 'FOLLOW_UP_COMPLETED', '§7.11 step 2: before MISSING_IDENTIFIER');
        $this->assertApiError($this->login($id, ['email' => 'nobody@acme.test']), 409, 'FOLLOW_UP_COMPLETED', '…and before the lookup');
    }

    public function testTheTokenLetsTheRespondentSaveAndAnInvalidOneIs401(): void
    {
        $id = $this->createAssignation($this->owner, $this->org, $this->questionnaireOf('ACME0001'), ['type' => 'default']);
        $login = $this->login($id, ['email' => 'ana@acme.test'])['json'];
        $body = ['session_id' => $login['questionnaire']['session_id'], 'questions' => $login['questionnaire']['questions']];

        self::assertSame(200, $this->api('PUT', '/api/v1/questionnaire/session', $body, headers: ['Authorization' => 'Bearer '.$login['token']])['status']);
        $this->assertApiError($this->api('PUT', '/api/v1/questionnaire/session', $body), 401, 'UNAUTHORIZED', 'D7: without the token, 401');
        $this->assertApiError($this->api('PUT', '/api/v1/questionnaire/session', $body, headers: ['Authorization' => 'Bearer rt.forged']), 401, 'UNAUTHORIZED', 'D7: an invalid token is 401, never a silent 200');
    }
}
