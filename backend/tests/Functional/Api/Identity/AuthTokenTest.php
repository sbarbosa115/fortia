<?php

namespace App\Tests\Functional\Api\Identity;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\Model\User;
use App\Tests\Support\ApiTestCase;

final class AuthTokenTest extends ApiTestCase
{
    public function testAUserSignsInWithEmailAndPasswordAndUsesTheIdToken(): void
    {
        $email = $this->account('ACME0001');
        $this->setPassword($email, 'correct-horse');

        $tokens = $this->data($this->api('POST', '/api/v1/auth/token', ['email' => 'ROOT@acme0001.test ', 'password' => 'correct-horse']));

        self::assertSame(86400, $tokens['expires_in'], 'PRD §13.1: id tokens last 24 h');
        $this->client->request('GET', '/api/v1/customer/onboarding', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$tokens['id_token']]);
        self::assertResponseIsSuccessful('the id token authenticates the next request');
    }

    public function testAWrongPasswordAndAnUnknownEmailGetTheSameAnswer(): void
    {
        $email = $this->account('ACME0001');
        $this->setPassword($email, 'correct-horse');

        $this->assertApiError($this->api('POST', '/api/v1/auth/token', ['email' => $email, 'password' => 'nope']), 401, 'INVALID_CREDENTIALS');
        $this->assertApiError($this->api('POST', '/api/v1/auth/token', ['email' => 'ghost@acme.test', 'password' => 'nope']), 401, 'INVALID_CREDENTIALS', 'never reveal which accounts exist');
    }

    public function testRepeatedFailuresAreRateLimited(): void
    {
        $email = $this->account('ACME0001');
        for ($i = 0; $i < 10; ++$i) {
            $this->api('POST', '/api/v1/auth/token', ['email' => $email, 'password' => 'nope']);
        }

        $this->assertApiError($this->api('POST', '/api/v1/auth/token', ['email' => $email, 'password' => 'nope']), 429, 'TOO_MANY_ATTEMPTS');
    }

    public function testARefreshTokenGivesANewSessionOnlyOnce(): void
    {
        $email = $this->account('ACME0001');
        $this->setPassword($email, 'correct-horse');
        $tokens = $this->data($this->api('POST', '/api/v1/auth/token', ['email' => $email, 'password' => 'correct-horse']));

        $renewed = $this->data($this->api('POST', '/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']]));
        self::assertNotSame($tokens['refresh_token'], $renewed['refresh_token'], 'refresh tokens rotate');

        $this->assertApiError($this->api('POST', '/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']]), 401, 'UNAUTHORIZED', 'a used refresh token is spent');
    }

    public function testARefreshTokenExpiresAfterThirtyDays(): void
    {
        $email = $this->account('ACME0001');
        $this->setPassword($email, 'correct-horse');
        $tokens = $this->data($this->api('POST', '/api/v1/auth/token', ['email' => $email, 'password' => 'correct-horse']));

        $this->clock()->set('+31 days');

        $this->assertApiError($this->api('POST', '/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']]), 401, 'UNAUTHORIZED');
    }

    private function setPassword(string $email, string $password): void
    {
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);
        $user->setPasswordHash(static::getContainer()->get(PasswordHasher::class)->hash($password), new \DateTimeImmutable());
        $this->em()->flush();
    }
}
