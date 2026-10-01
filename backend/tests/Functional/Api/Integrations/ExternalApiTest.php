<?php

namespace App\Tests\Functional\Api\Integrations;

use App\Billing\Application\Usage;
use App\Integrations\Domain\Model\ApiKey;
use App\Tests\Functional\Api\Responses\SessionFixtures;
use App\Tests\Support\ApiTestCase;

/** The external API with X-API-Key (PRD §8.11 GET /external/*, §16.1 #2, §16.3 #14). */
final class ExternalApiTest extends ApiTestCase
{
    use SessionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock()->set('2026-09-30T12:00:00Z');
    }

    public function testAKeyListsItsAccountsQuestionnairesNewestFirstAndCountsTheCall(): void
    {
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01');
        $first = $this->questionnaire('ACME0001');
        $second = $this->questionnaire('ACME0001', 'diagnostic');
        $this->em()->getConnection()->executeStatement("UPDATE questionnaire SET created_at = '2026-09-02 00:00:00' WHERE questionnaire_id = ?", [$second]);
        $flowId = $this->flow('ACME0001', $first, [self::state('a', 'questionnaire')]);
        $this->questionnaire('GLOBEX01');
        $key = $this->createKey($owner);

        $data = $this->data($this->external('/api/v1/external/questionnaires', $key));

        self::assertSame([$second, $first], array_column($data['questionnaires'], 'id'), '§8.11: newest first, only the key\'s account');
        self::assertSame(['id', 'flow_id', 'slug', 'title', 'description', 'is_active', 'type', 'created_at', 'updated_at'], array_keys($data['questionnaires'][1]), '§8.11 shape');
        self::assertSame($flowId, $data['questionnaires'][1]['flow_id']);
        self::assertSame(['page' => 1, 'page_size' => 50, 'total_items' => 2, 'total_pages' => 1, 'has_next' => false, 'has_previous' => false], $data['pagination'], '§8.1 numbered pagination, page_size default 50');
        self::assertSame(1, static::getContainer()->get(Usage::class)->current('ACME0001')['api'] ?? 0, '§7.2: each call to the external API counts one "api"');
        $this->em()->clear();
        self::assertNotNull($this->em()->find(ApiKey::class, hash('sha256', $key))?->lastUsedAt(), '§8.11: sets last_used_at');
    }

    public function testThePageSizeIsAtMostFifty(): void
    {
        $owner = $this->account('ACME0001');
        $this->questionnaire('ACME0001');
        $this->questionnaire('ACME0001');
        $key = $this->createKey($owner);

        $big = $this->data($this->external('/api/v1/external/questionnaires?page_size=500', $key));
        $paged = $this->data($this->external('/api/v1/external/questionnaires?page=2&page_size=1', $key));

        self::assertSame(50, $big['pagination']['page_size'], '§8.11: max. 50');
        self::assertCount(1, $paged['questionnaires']);
        self::assertSame(['page' => 2, 'page_size' => 1, 'total_items' => 2, 'total_pages' => 2, 'has_next' => false, 'has_previous' => true], $paged['pagination']);
    }

    public function testAMissingUnknownRevokedOrExpiredKeyGetsTheSame401(): void
    {
        $owner = $this->account('ACME0001');
        $revoked = $this->createKey($owner);
        self::assertSame(204, $this->api('DELETE', '/api/v1/api-keys/'.hash('sha256', $revoked), as: $owner)['status']);
        $expiring = $this->data($this->api('POST', '/api/v1/api-keys', ['name' => 'Short', 'expiration_days' => 1], as: $owner), 201)['api_key'];

        $missing = $this->api('GET', '/api/v1/external/questionnaires');
        $this->assertApiError($missing, 401, 'INVALID_API_KEY', 'missing');
        $this->assertApiError($this->external('/api/v1/external/questionnaires', 'QAIRE-'.str_repeat('0', 64)), 401, 'INVALID_API_KEY', 'unknown');
        $this->assertApiError($this->external('/api/v1/external/questionnaires', $revoked), 401, 'INVALID_API_KEY', '§16.3 #14: revoked key → 401');
        $this->data($this->external('/api/v1/external/questionnaires', $expiring));
        $this->clock()->set('2026-10-01T12:00:01Z');
        $expired = $this->external('/api/v1/external/questionnaires', $expiring);
        $this->assertApiError($expired, 401, 'INVALID_API_KEY', 'expired');
        self::assertSame($missing['json']['error']['message'], $expired['json']['error']['message'], '§8.11: the same message whether missing, revoked or expired');
        self::assertSame(401, $this->api('GET', '/api/v1/external/questionnaires', as: $owner)['status'], 'a console token is not an API key');
    }

    public function testAnswersComeInTheWebhookFormatNewestFirst(): void
    {
        $owner = $this->account('ACME0001');
        $questionnaireId = $this->questionnaire('ACME0001', questions: [self::textQuestion('q1', 'Tell us about you'), self::radioQuestion('q2', null, ['1', '2', '3'])]);
        $this->clock()->set('2026-09-30T10:00:00Z');
        $older = $this->answer($questionnaireId, ['q1' => 'Hello', 'q2' => '2']);
        $this->clock()->set('2026-09-30T11:00:00Z');
        $newer = $this->answer($questionnaireId, ['q1' => 'Hi again']);
        $this->clock()->set('2026-09-30T12:00:00Z');
        $key = $this->createKey($owner);

        $data = $this->data($this->external('/api/v1/external/questionnaires/'.$questionnaireId.'/answers', $key));

        self::assertSame($questionnaireId, $data['questionnaire_id']);
        self::assertSame([$newer, $older], array_column($data['sessions'], 'id'), '§8.11: newest first');
        self::assertSame([['title' => 'Tell us about you', 'value' => 'Hello'], ['title' => 'Question q2', 'value' => 'Option 2']], $data['sessions'][1]['answers'], '§7.14: text as a string, numeric selections as their labels');
        self::assertSame(2, $data['pagination']['total_items']);
    }

    public function testAnotherAccountsQuestionnaireIs404(): void
    {
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01');
        $foreign = $this->questionnaire('GLOBEX01');
        $key = $this->createKey($owner);

        $this->assertApiError($this->external('/api/v1/external/questionnaires/'.$foreign.'/answers', $key), 404, 'QUESTIONNAIRE_NOT_FOUND', '§8.11: 404 if it belongs to another account');
        $this->assertApiError($this->external('/api/v1/external/questionnaires/not-a-uuid/answers', $key), 400, 'INVALID_UUID');
        $this->assertApiError($this->api('GET', '/api/v1/external/questionnaires/'.$foreign.'/answers'), 401, 'INVALID_API_KEY', 'the key comes first');
    }

    public function testAnExhaustedApiQuotaRefusesTheCall(): void
    {
        $owner = $this->account('ACME0001');
        $key = $this->createKey($owner);
        static::getContainer()->get(Usage::class)->set('ACME0001', ['api' => 5000]);
        $this->em()->flush();

        $response = $this->external('/api/v1/external/questionnaires', $key);

        $this->assertApiError($response, 429, 'PLAN_LIMIT_REACHED', '§8.11: Cap(api) on every call');
        self::assertSame('FEATURE_LIMIT_REACHED', $response['json']['error']['details']['reason'] ?? null);
        self::assertSame(5000, static::getContainer()->get(Usage::class)->current('ACME0001')['api'] ?? 0, 'a refused call does not count');
    }

    private function createKey(string $as): string
    {
        return (string) $this->data($this->api('POST', '/api/v1/api-keys', ['name' => 'Integration'], as: $as), 201)['api_key'];
    }

    /** @return array{status: int, json: mixed, body: string} */
    private function external(string $uri, string $key): array
    {
        return $this->api('GET', $uri, headers: ['X-API-Key' => $key]);
    }

    /** @param array<string, string> $values */
    private function answer(string $questionnaireId, array $values): string
    {
        $session = $this->startSession($questionnaireId);
        $this->data($this->api('POST', '/api/v1/questionnaire/session', self::answered($session, $values)));

        return (string) $session['session_id'];
    }
}
