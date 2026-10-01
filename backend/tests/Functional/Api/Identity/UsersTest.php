<?php

namespace App\Tests\Functional\Api\Identity;

use App\Billing\Application\Usage;
use App\Identity\Domain\Model\User;
use App\Tests\Support\ApiTestCase;

final class UsersTest extends ApiTestCase
{
    private const NEW_USER = ['email' => 'New.Member@Acme.test', 'password' => 'correct-horse', 'name' => 'Nico Member', 'role' => 'Customer-Read-Only'];

    public function testAnAdminCreatesATeamUserWhoCanSignIn(): void
    {
        $owner = $this->account('ACME0001');

        $data = $this->data($this->api('POST', '/api/v1/users', self::NEW_USER, as: $owner), 201);

        self::assertSame(['email' => 'new.member@acme.test', 'name' => 'Nico Member', 'root' => false, 'role' => 'Customer-Read-Only', 'customer_id' => 'ACME0001'], $data, 'PRD §8.2 POST /users output');
        $this->data($this->api('POST', '/api/v1/auth/token', ['email' => 'new.member@acme.test', 'password' => 'correct-horse']), 200);
    }

    public function testCreatingAUserCountsAsUsersUsage(): void
    {
        $owner = $this->account('ACME0001');

        $this->api('POST', '/api/v1/users', self::NEW_USER, as: $owner);

        self::assertSame(1, static::getContainer()->get(Usage::class)->current('ACME0001')['users'] ?? 0, 'PRD §7.2: creating a team user counts "users"');
        $count = (int) $this->em()->getConnection()->fetchOne("SELECT COUNT(*) FROM domain_event_log WHERE event_type = 'UserCreated' AND customer_id = 'ACME0001'");
        self::assertSame(1, $count, 'PRD §8.2: UserCreated event');
    }

    public function testAReadOnlyMemberCannotCreateUsers(): void
    {
        $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);

        $this->assertApiError($this->api('POST', '/api/v1/users', self::NEW_USER, as: 'reader@acme.test'), 403, 'FORBIDDEN', 'PRD §8.2: POST /users is AG');
    }

    public function testTheUsersQuotaIsEnforced(): void
    {
        $owner = $this->account('GLOBEX01', plan: 'starter');
        static::getContainer()->get(Usage::class)->set('GLOBEX01', ['users' => 2]);
        $this->em()->flush();

        $response = $this->api('POST', '/api/v1/users', self::NEW_USER, as: $owner);

        $this->assertApiError($response, 429, 'PLAN_LIMIT_REACHED', 'PRD §8.2: Cap(users)');
        self::assertSame(['reason' => 'FEATURE_LIMIT_REACHED', 'feature' => 'users'], $response['json']['error']['details']);
    }

    public function testAnEmailInUseAnywhereIsAConflict(): void
    {
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01');

        $this->assertApiError($this->api('POST', '/api/v1/users', ['email' => 'root@globex01.test'] + self::NEW_USER, as: $owner), 409, 'EMAIL_ALREADY_EXISTS', 'PRD §4.3: emails are unique across the whole system');
    }

    public function testOnlyAssignableRolesAreAccepted(): void
    {
        $owner = $this->account('ACME0001');

        $this->assertApiError($this->api('POST', '/api/v1/users', ['role' => 'Admin'] + self::NEW_USER, as: $owner), 400, 'INVALID_ROLE', 'PRD §4.2: Admin is only granted by hand');
        $this->assertApiError($this->api('POST', '/api/v1/users', ['password' => 'short'] + self::NEW_USER, as: $owner), 400, 'VALIDATION_ERROR');
    }

    public function testTheListShowsOnlyTheCallersAccountRootFirstThenByName(): void
    {
        $owner = $this->account('ACME0001');
        $this->user('ACME0001', 'zoe@acme.test', ['Customer-Admin'], name: 'Zoe');
        $this->user('ACME0001', 'bea@acme.test', ['Customer-Read-Only'], name: 'Bea');
        $this->account('GLOBEX01');

        $users = $this->data($this->api('GET', '/api/v1/users', as: 'bea@acme.test'))['users'];

        self::assertSame([$owner, 'bea@acme.test', 'zoe@acme.test'], array_column($users, 'email'), 'PRD §8.2: root first, then by name; never another tenant');
        self::assertSame(['email' => 'bea@acme.test', 'name' => 'Bea', 'root' => false, 'role' => 'Customer-Read-Only', 'customer_id' => 'ACME0001'], $users[1]);
    }

    public function testListingNeedsASignIn(): void
    {
        $this->assertApiError($this->api('GET', '/api/v1/users'), 401, 'UNAUTHORIZED');
    }

    public function testAnAssumingAdminCreatesUsersInTheAssumedAccount(): void
    {
        $this->account('ACME0001');
        $admin = $this->admin();

        $this->data($this->api('POST', '/api/v1/users', self::NEW_USER, as: $admin, headers: ['X-Assume-Customer-Id' => 'ACME0001']), 201);

        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'new.member@acme.test']);
        self::assertSame('ACME0001', $user?->customerId(), 'PRD §4.4: the request runs as the assumed account');
    }
}
