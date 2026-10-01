<?php

namespace App\Tests\Support;

use App\Identity\Domain\Model\Customer;
use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;
use App\Shared\Infrastructure\Llm\Fake\FakeLanguageModel;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The base of every functional test of the API (tests/Functional/Api/<Context>/…). Each test runs in a transaction
 * that is rolled back (DAMA) and builds the accounts it needs:
 *
 *     $acme = $this->account('ACME0001');           // root user root@ACME0001.test
 *     $reader = $this->user('ACME0001', 'reader@acme.test', groups: ['Customer-Read-Only']);
 *     $response = $this->api('GET', '/api/v1/customer/ACME0001/settings', as: 'root@ACME0001.test');
 *     self::assertSame(200, $response['status']);
 *     $this->assertApiError($response, 404, 'QUESTIONNAIRE_NOT_FOUND');
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->clock()->reset();
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function clock(): TestClock
    {
        // The test container aliases the Clock to TestClock (config/services.yaml, when@test).
        return static::getContainer()->get(Clock::class);
    }

    protected function llm(): FakeLanguageModel
    {
        return static::getContainer()->get(FakeLanguageModel::class);
    }

    /** An account with its root user (root@{customerId}.test). */
    protected function account(string $customerId, string $language = 'es-CO', bool $onboarding = true): string
    {
        $now = $this->clock()->now();
        $this->em()->persist(new Customer($customerId, $language, 'default', $now, $onboarding));
        $this->em()->flush();
        $this->user($customerId, 'root@'.strtolower($customerId).'.test', root: true);

        return 'root@'.strtolower($customerId).'.test';
    }

    /** @param list<string> $groups */
    protected function user(string $customerId, string $email, array $groups = ['Customer-Admin'], bool $root = false, string $name = 'Test User'): User
    {
        $user = new User(Ids::uuid4(), $email, $name, $customerId, $root, $groups, $this->clock()->now());
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    /** A super-admin (group Admin) in its own account. */
    protected function admin(string $email = 'admin@mappi.test'): string
    {
        $now = $this->clock()->now();
        $this->em()->persist(new Customer('MAPPI001', 'en-US', 'default', $now));
        $this->em()->flush();
        $this->user('MAPPI001', $email, ['Admin'], true, 'Platform Admin');

        return $email;
    }

    protected function tokenFor(string $email): string
    {
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user, "No test user $email: create it with account() or user() first");

        return static::getContainer()->get(JWTTokenManagerInterface::class)->create(SecurityUser::of($user));
    }

    /**
     * A JSON request. $as signs it in as that console user; $body is sent as JSON (a string is sent as is).
     *
     * @param array<string, mixed>|string|null $body
     * @param array<string, string>            $headers e.g. ['X-Assume-Customer-Id' => 'GLOBEX01']
     *
     * @return array{status: int, json: mixed, body: string}
     */
    protected function api(string $method, string $uri, array|string|null $body = null, ?string $as = null, array $headers = []): array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if (null !== $as) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$this->tokenFor($as);
        }
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $content = \is_string($body) ? $body : (null === $body ? null : (string) json_encode($body));
        $this->client->request($method, $uri, [], [], $server, $content);
        $response = $this->client->getResponse();
        $raw = (string) $response->getContent();

        return ['status' => $response->getStatusCode(), 'json' => '' === $raw ? null : json_decode($raw, true), 'body' => $raw];
    }

    /** @param array{status: int, json: mixed, body: string} $response */
    protected function assertApiError(array $response, int $status, string $code, string $why = ''): void
    {
        self::assertSame($status, $response['status'], trim($why.' — body: '.$response['body']));
        self::assertIsArray($response['json']);
        self::assertSame($code, $response['json']['error']['code'] ?? null, trim($why.' — body: '.$response['body']));
    }

    /**
     * The "data" of a {message, data} envelope, asserting the status.
     *
     * @param array{status: int, json: mixed, body: string} $response
     */
    protected function data(array $response, int $status = 200): mixed
    {
        self::assertSame($status, $response['status'], 'body: '.$response['body']);
        self::assertIsArray($response['json']);
        self::assertArrayHasKey('data', $response['json'], 'body: '.$response['body']);

        return $response['json']['data'];
    }
}
