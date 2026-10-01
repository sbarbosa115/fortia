<?php

namespace App\Tests\Functional\Api\Branding;

use App\Billing\Application\Usage;
use App\Branding\Domain\Model\CustomerStyles;
use App\Tests\Support\ApiTestCase;

/** PRD §8.5 POST /styles (AG, Cap(styles)) and the console's read of its own brand through GET /styles. */
final class StylesApiTest extends ApiTestCase
{
    public function testAnAdminStartsTheStylesJobAndGets202WithTheJob(): void
    {
        $owner = $this->account('ACME0001');

        $response = $this->api('POST', '/api/v1/styles', ['website' => '', 'styles' => ['a' => ['color' => '#8249df']]], as: $owner);

        $job = $this->data($response, 202)['job'];
        self::assertSame('styles', $job['job_type'], '§8.5: POST /styles answers with the styles job');
        self::assertArrayHasKey('job_id', $job);
        self::assertArrayNotHasKey('payload', $job, '§8.5 GET /jobs: never the payload');
    }

    public function testTheBodyIsValidatedBeforeAnythingRuns(): void
    {
        $owner = $this->account('ACME0001');

        $response = $this->api('POST', '/api/v1/styles', ['styles' => ['body' => ['background' => 'red'], 'shadow' => []]], as: $owner);

        $this->assertApiError($response, 400, 'VALIDATION_ERROR', '§8.5: styles are validated');
        $fields = array_column($response['json']['error']['details']['violations'], 'field');
        self::assertSame(['styles.body.background', 'styles.shadow'], $fields);
        $this->assertApiError($this->api('POST', '/api/v1/styles', ['website' => 'ftp://acme.test'], as: $owner), 400, 'VALIDATION_ERROR', 'only http(s) websites');
        $this->assertApiError($this->api('POST', '/api/v1/styles', ['website' => 'https://acme.test', 'theme' => 'dark'], as: $owner), 400, 'VALIDATION_ERROR', 'no extra fields');
    }

    public function testOnlyTheAdminGroupsMayChangeTheBrand(): void
    {
        $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);

        $this->assertApiError($this->api('POST', '/api/v1/styles', ['website' => ''], as: 'reader@acme.test'), 403, 'FORBIDDEN', '§8.5 AG');
        $this->assertApiError($this->api('POST', '/api/v1/styles', ['website' => '']), 401, 'UNAUTHORIZED');
    }

    public function testThePlanMustHaveStylesLeft(): void
    {
        $owner = $this->account('ACME0001', plan: 'starter');
        static::getContainer()->get(Usage::class)->set('ACME0001', ['styles' => 2]);
        $this->em()->flush();

        $response = $this->api('POST', '/api/v1/styles', ['website' => ''], as: $owner);

        $this->assertApiError($response, 429, 'PLAN_LIMIT_REACHED', '§8.5 Cap(styles): the starter plan allows 2 a month');
        self::assertSame('styles', $response['json']['error']['details']['feature'] ?? null);
    }

    public function testValidationComesBeforeThePlanGate(): void
    {
        $owner = $this->account('ACME0001', plan: 'starter');
        static::getContainer()->get(Usage::class)->set('ACME0001', ['styles' => 2]);
        $this->em()->flush();

        $this->assertApiError($this->api('POST', '/api/v1/styles', ['styles' => ['a' => ['color' => 'blue']]], as: $owner), 400, 'VALIDATION_ERROR', '§5 A2: validation → plan gate');
    }

    public function testTheConsoleReadsItsOwnWebsiteButStrangersDoNot(): void
    {
        $owner = $this->account('ACME0001');
        $globex = $this->account('GLOBEX01');
        $this->em()->persist(new CustomerStyles('ACME0001', 'https://acme.test', ['a' => ['color' => '#123456']], new \DateTimeImmutable()));
        $this->em()->flush();

        $own = $this->data($this->api('GET', '/api/v1/styles?customer_id=ACME0001', as: $owner));
        $anonymous = $this->data($this->api('GET', '/api/v1/styles?customer_id=ACME0001'));
        $stranger = $this->data($this->api('GET', '/api/v1/styles?customer_id=ACME0001', as: $globex));

        self::assertSame('https://acme.test', $own['website'], 'the Customization screen shows the stored website');
        self::assertNull($anonymous['website'], 'the public read gives no website');
        self::assertNull($stranger['website'], "another tenant never reads an account's website");
        self::assertSame($own['styles'], $stranger['styles'], 'the styles themselves stay public (§8.5 P)');
    }
}
