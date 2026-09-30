<?php

namespace App\Tests\Functional\Api\Organizations;

use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\Model\Project;
use App\Organizations\Domain\Model\OrganizationUser;
use App\Shared\Domain\Ids;
use App\Shared\Infrastructure\Persistence\Model\DomainEventRecord;
use App\Tests\Support\ApiTestCase;

/** PRD §8.7 organizations and members, with D1 (ownership) and D2 (no orphaned members). */
final class OrganizationApiTest extends ApiTestCase
{
    private const URL = '/api/v1/organizations';

    public function testAnAdminGroupUserCreatesAnOrganizationWithItsMembers(): void
    {
        $owner = $this->account('ACME0001');

        $org = $this->data($this->api('POST', self::URL, [
            'name' => 'Acme Retail',
            'domain_email' => 'acme.test',
            'description' => 'Stores',
            'organization_users' => [
                ['name' => 'José Pérez', 'email' => 'Jose@Acme.test', 'phone' => '+57 300 111', 'role' => 'Lead', 'area' => 'Sales'],
                ['name' => 'Bruno', 'phone' => '+57 300 222'],
            ],
        ], as: $owner), 201);

        self::assertTrue(Ids::isUuid4($org['organization_id']));
        self::assertSame('ACME0001', $org['customer_id']);
        self::assertSame('Acme Retail', $org['name']);
        self::assertSame('acme.test', $org['domain_email']);
        self::assertTrue($org['active'], 'PRD §8.7: active defaults to true');
        self::assertCount(2, $org['organization_users']);
        $jose = $org['organization_users'][1];
        self::assertSame('jose perez', $jose['name'], 'PRD §6.13: names are normalized');
        self::assertSame('jose@acme.test', $jose['email']);
        self::assertSame('+57300111', $jose['phone']);
        self::assertSame($org['organization_id'], $jose['organization_id']);
    }

    public function testCreatingCountsOneOrganizationAndRecordsTheEvent(): void
    {
        $owner = $this->account('GLOBEX01', plan: 'starter');

        $this->data($this->api('POST', self::URL, ['name' => 'One'], as: $owner), 201);

        $usage = $this->data($this->api('GET', '/api/v1/customer/usage', as: $owner));
        self::assertSame(1, $usage['features']['organizations']['used'], 'PRD §7.2: creating an organization counts as organizations');
        self::assertSame(1, $this->eventCount('OrganizationCreated', 'GLOBEX01'), 'PRD §12: OrganizationCreated');
    }

    public function testTheOrganizationsCapacityGateStopsTheThirdOnStarter(): void
    {
        $owner = $this->account('GLOBEX01', plan: 'starter');
        $this->data($this->api('POST', self::URL, ['name' => 'One'], as: $owner), 201);
        $this->data($this->api('POST', self::URL, ['name' => 'Two'], as: $owner), 201);

        $response = $this->api('POST', self::URL, ['name' => 'Three'], as: $owner);

        $this->assertApiError($response, 429, 'PLAN_LIMIT_REACHED', 'PRD §8.7: Cap(organizations), Starter allows 2');
        self::assertSame('organizations', $response['json']['error']['details']['feature']);
    }

    public function testAReadOnlyUserCannotCreate(): void
    {
        $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);

        $this->assertApiError($this->api('POST', self::URL, ['name' => 'X'], as: 'reader@acme.test'), 403, 'FORBIDDEN', 'PRD §8.7: POST is AG');
    }

    public function testExtraFieldsAndBadMembersAreValidationErrors(): void
    {
        $owner = $this->account('ACME0001');

        $this->assertApiError($this->api('POST', self::URL, ['name' => 'X', 'color' => 'red'], as: $owner), 400, 'VALIDATION_ERROR', 'no extra fields');
        $this->assertApiError($this->api('POST', self::URL, ['name' => 'X', 'organization_users' => [['name' => 'A', 'email' => 'a@x.test', 'nick' => 'a']]], as: $owner), 400, 'VALIDATION_ERROR', 'no extra fields in members either');
        $this->assertApiError($this->api('POST', self::URL, ['name' => '   '], as: $owner), 400, 'VALIDATION_ERROR', 'name not empty');
        $this->assertApiError($this->api('POST', self::URL, ['name' => str_repeat('a', 121)], as: $owner), 400, 'VALIDATION_ERROR', 'name ≤ 120');
        $this->assertApiError($this->api('POST', self::URL, ['name' => 'X', 'domain_email' => 'not a domain'], as: $owner), 400, 'VALIDATION_ERROR', 'domain-shaped');
        $this->assertApiError($this->api('POST', self::URL, ['name' => 'X', 'description' => str_repeat('a', 1001)], as: $owner), 400, 'VALIDATION_ERROR', 'description ≤ 1000');

        $response = $this->api('POST', self::URL, ['name' => 'X', 'organization_users' => [['name' => 'Ana']]], as: $owner);
        $this->assertApiError($response, 400, 'VALIDATION_ERROR', 'each member with an email or phone');
        self::assertStringContainsString('organization_users[0]', $response['json']['error']['message']);

        $response = $this->api('POST', self::URL, ['name' => 'X', 'organization_users' => [['name' => 'Ana', 'email' => 'a@x.test'], ['name' => 'B', 'email' => 'A@x.test']]], as: $owner);
        $this->assertApiError($response, 400, 'VALIDATION_ERROR', 'unique emails in the list');
        self::assertStringContainsString('organization_users[1].email', $response['json']['error']['message']);
    }

    public function testTheNameIsTrimmedAndItsSpacesCollapsed(): void
    {
        $owner = $this->account('ACME0001');

        $org = $this->data($this->api('POST', self::URL, ['name' => '  Acme   Retail '], as: $owner), 201);

        self::assertSame('Acme Retail', $org['name'], 'PRD §8.7: no extra whitespace');
    }

    public function testADomainUsedByAnotherOrganizationOfAnyAccountIsAConflict(): void
    {
        $this->account('GLOBEX01', plan: 'starter');
        $acme = $this->account('ACME0001');
        $this->data($this->api('POST', self::URL, ['name' => 'Globex', 'domain_email' => 'shared.test'], as: 'root@globex01.test'), 201);

        $this->assertApiError($this->api('POST', self::URL, ['name' => 'Acme', 'domain_email' => 'SHARED.test'], as: $acme), 409, 'DOMAIN_EMAIL_CONFLICT', 'PRD §6.12: domain_email is unique across the whole system');
    }

    public function testKeepingItsOwnDomainOnUpdateIsNotAConflict(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->data($this->api('POST', self::URL, ['name' => 'Acme', 'domain_email' => 'acme.test'], as: $owner), 201);

        $updated = $this->data($this->api('PUT', self::URL.'/'.$org['organization_id'], ['domain_email' => 'acme.test', 'name' => 'Acme 2'], as: $owner));

        self::assertSame('Acme 2', $updated['name']);
    }

    public function testTheListingIsBareAndOnlyShowsTheCallersOrganizationsWithTheirMembers(): void
    {
        $acme = $this->account('ACME0001');
        $globex = $this->account('GLOBEX01', plan: 'starter');
        $this->data($this->api('POST', self::URL, ['name' => 'Acme A', 'organization_users' => [['name' => 'Ana', 'email' => 'ana@a.test']]], as: $acme), 201);
        $this->data($this->api('POST', self::URL, ['name' => 'Globex G'], as: $globex), 201);

        $response = $this->api('GET', self::URL, as: $acme);

        self::assertSame(200, $response['status']);
        self::assertArrayNotHasKey('data', $response['json'], 'PRD §8.7: GET /organizations is bare');
        self::assertSame(['Acme A'], array_column($response['json']['organizations'], 'name'), 'PRD §4.3: filtered by the caller\'s account');
        self::assertSame('ana', $response['json']['organizations'][0]['organization_users'][0]['name']);
    }

    public function testAReadOnlyUserMayList(): void
    {
        $owner = $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);
        $this->data($this->api('POST', self::URL, ['name' => 'Acme A'], as: $owner), 201);

        $response = $this->api('GET', self::URL, as: 'reader@acme.test');

        self::assertCount(1, $response['json']['organizations']);
    }

    public function testAnAdminSeesEveryAccountsOrganizations(): void
    {
        $admin = $this->admin();
        $this->data($this->api('POST', self::URL, ['name' => 'Acme A'], as: $this->account('ACME0001')), 201);
        $this->data($this->api('POST', self::URL, ['name' => 'Globex G'], as: $this->account('GLOBEX01', plan: 'starter')), 201);

        $names = array_column($this->api('GET', self::URL, as: $admin)['json']['organizations'], 'name');

        self::assertEqualsCanonicalizing(['Acme A', 'Globex G'], $names, 'PRD §8.7: Admin sees all');
    }

    public function testTheListingReadsMembersInOneQueryWhateverTheNumberOfOrganizations(): void
    {
        $owner = $this->account('ACME0001');
        for ($i = 1; $i <= 2; ++$i) {
            $this->data($this->api('POST', self::URL, ['name' => "Org $i", 'organization_users' => [['name' => 'A', 'email' => "a$i@x.test"]]], as: $owner), 201);
        }
        $few = $this->listingQueryCount($owner);
        self::assertGreaterThan(0, $few, 'the counter sees the queries');
        for ($i = 3; $i <= 6; ++$i) {
            $this->data($this->api('POST', self::URL, ['name' => "Org $i", 'organization_users' => [['name' => 'A', 'email' => "a$i@x.test"]]], as: $owner), 201);
        }

        self::assertSame($few, $this->listingQueryCount($owner), 'steps/04: no N+1, the query count does not grow with the rows');
    }

    public function testAnUpdateReconcilesTheMembers(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->data($this->api('POST', self::URL, ['name' => 'Acme', 'organization_users' => [
            ['name' => 'Ana', 'email' => 'ana@acme.test'],
            ['name' => 'Bruno', 'phone' => '+57300'],
            ['name' => 'Carla', 'email' => 'carla@acme.test'],
        ]], as: $owner), 201);
        $byName = array_column($org['organization_users'], null, 'name');

        $updated = $this->data($this->api('PUT', self::URL.'/'.$org['organization_id'], ['organization_users' => [
            ['organization_user_id' => $byName['ana']['organization_user_id'], 'name' => 'Ana María', 'email' => 'ana@acme.test'],
            ['name' => 'Bruno', 'phone' => '+57 300', 'role' => 'Lead'],
            ['name' => 'Dana', 'email' => 'dana@acme.test'],
        ]], as: $owner));

        $after = array_column($updated['organization_users'], null, 'name');
        self::assertSame(['ana maria', 'bruno', 'dana'], array_keys($after));
        self::assertSame($byName['ana']['organization_user_id'], $after['ana maria']['organization_user_id'], 'matched by id: keeps its id');
        self::assertSame($byName['bruno']['organization_user_id'], $after['bruno']['organization_user_id'], 'matched by name + phone: keeps its id');
        self::assertSame('Lead', $after['bruno']['role']);
        self::assertNull($this->em()->find(OrganizationUser::class, $byName['carla']['organization_user_id']), 'PRD §8.7: members that match nothing are deleted');
        self::assertSame('Acme', $updated['name'], 'partial: the name was not sent');
    }

    public function testMovingAnEmailFromADeletedMemberToAnotherDoesNotClash(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->data($this->api('POST', self::URL, ['name' => 'Acme', 'organization_users' => [
            ['name' => 'Ana', 'email' => 'ana@acme.test'],
            ['name' => 'Bruno', 'email' => 'bruno@acme.test'],
        ]], as: $owner), 201);
        $byName = array_column($org['organization_users'], null, 'name');

        // Ana takes Bruno's email and Bruno is dropped; then a swap of two emails.
        $updated = $this->data($this->api('PUT', self::URL.'/'.$org['organization_id'], ['organization_users' => [
            ['organization_user_id' => $byName['ana']['organization_user_id'], 'name' => 'Ana', 'email' => 'bruno@acme.test'],
        ]], as: $owner));
        self::assertSame(['bruno@acme.test'], array_column($updated['organization_users'], 'email'));

        $org2 = $this->data($this->api('POST', self::URL, ['name' => 'Swap', 'organization_users' => [
            ['name' => 'X', 'email' => 'x@acme.test'],
            ['name' => 'Y', 'email' => 'y@acme.test'],
        ]], as: $owner), 201);
        $ids = array_column($org2['organization_users'], 'organization_user_id', 'name');
        $swapped = $this->data($this->api('PUT', self::URL.'/'.$org2['organization_id'], ['organization_users' => [
            ['organization_user_id' => $ids['x'], 'name' => 'X', 'email' => 'y@acme.test'],
            ['organization_user_id' => $ids['y'], 'name' => 'Y', 'email' => 'x@acme.test'],
        ]], as: $owner));
        self::assertSame(['x' => 'y@acme.test', 'y' => 'x@acme.test'], array_column($swapped['organization_users'], 'email', 'name'));
    }

    public function testAnUpdateNeedsAtLeastOneField(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->data($this->api('POST', self::URL, ['name' => 'Acme'], as: $owner), 201);

        $this->assertApiError($this->api('PUT', self::URL.'/'.$org['organization_id'], [], as: $owner), 400, 'VALIDATION_ERROR', 'PRD §8.7: partial, at least one field');
        $this->assertApiError($this->api('PUT', self::URL.'/'.$org['organization_id'], ['name' => null], as: $owner), 400, 'VALIDATION_ERROR', 'the name cannot be cleared');
    }

    public function testNullClearsTheDomainAndTheDescription(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->data($this->api('POST', self::URL, ['name' => 'Acme', 'domain_email' => 'acme.test', 'description' => 'D'], as: $owner), 201);

        $updated = $this->data($this->api('PUT', self::URL.'/'.$org['organization_id'], ['domain_email' => null, 'description' => null, 'active' => false], as: $owner));

        self::assertNull($updated['domain_email']);
        self::assertNull($updated['description']);
        self::assertFalse($updated['active']);
    }

    public function testAnotherTenantsOrganizationIs404ForUpdateAndDelete(): void
    {
        $globex = $this->account('GLOBEX01', plan: 'starter');
        $acme = $this->account('ACME0001');
        $org = $this->data($this->api('POST', self::URL, ['name' => 'Globex'], as: $globex), 201);

        $this->assertApiError($this->api('PUT', self::URL.'/'.$org['organization_id'], ['name' => 'Mine'], as: $acme), 404, 'ORGANIZATION_NOT_FOUND', 'D1: require Own; another tenant is 404, never 403');
        $this->assertApiError($this->api('DELETE', self::URL.'/'.$org['organization_id'], as: $acme), 404, 'ORGANIZATION_NOT_FOUND', 'D1');
        self::assertSame('Globex', $this->api('GET', self::URL, as: $globex)['json']['organizations'][0]['name'], 'untouched');
    }

    public function testAnAdminMayUpdateAnyAccountsOrganization(): void
    {
        $admin = $this->admin();
        $org = $this->data($this->api('POST', self::URL, ['name' => 'Acme'], as: $this->account('ACME0001')), 201);

        $updated = $this->data($this->api('PUT', self::URL.'/'.$org['organization_id'], ['name' => 'By admin'], as: $admin));

        self::assertSame('By admin', $updated['name']);
        self::assertSame('ACME0001', $updated['customer_id'], 'stays in its account');
    }

    public function testAReadOnlyUserCannotChangeOrDelete(): void
    {
        $owner = $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);
        $org = $this->data($this->api('POST', self::URL, ['name' => 'Acme'], as: $owner), 201);

        $this->assertApiError($this->api('PUT', self::URL.'/'.$org['organization_id'], ['name' => 'X'], as: 'reader@acme.test'), 403, 'FORBIDDEN', 'PRD §4.2: a read-only role has no write permission');
        $this->assertApiError($this->api('DELETE', self::URL.'/'.$org['organization_id'], as: 'reader@acme.test'), 403, 'FORBIDDEN');
    }

    public function testUnknownAndMalformedIds(): void
    {
        $owner = $this->account('ACME0001');

        $this->assertApiError($this->api('PUT', self::URL.'/'.Ids::uuid4(), ['name' => 'X'], as: $owner), 404, 'ORGANIZATION_NOT_FOUND');
        $this->assertApiError($this->api('DELETE', self::URL.'/nope', as: $owner), 400, 'INVALID_UUID');
    }

    public function testDeletingRemovesItsMembersCountsAndRecordsTheEvent(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->data($this->api('POST', self::URL, ['name' => 'Acme', 'organization_users' => [['name' => 'Ana', 'email' => 'ana@acme.test']]], as: $owner), 201);
        $memberId = $org['organization_users'][0]['organization_user_id'];

        $response = $this->api('DELETE', self::URL.'/'.$org['organization_id'], as: $owner);

        self::assertSame(204, $response['status']);
        self::assertSame('', $response['body']);
        self::assertSame([], $this->api('GET', self::URL, as: $owner)['json']['organizations']);
        $this->em()->clear();
        self::assertNull($this->em()->find(OrganizationUser::class, $memberId), 'D2: deleting an organization deletes its members');
        self::assertSame(1, $this->eventCount('OrganizationDeleted', 'ACME0001'), 'PRD §12: OrganizationDeleted');
        $usage = $this->data($this->api('GET', '/api/v1/customer/usage', as: $owner));
        self::assertSame(2, $usage['features']['organizations']['used'], 'PRD §7.2: create and delete both count');
    }

    public function testAnOrganizationWithAssignationsCannotBeDeleted(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->data($this->api('POST', self::URL, ['name' => 'Acme'], as: $owner), 201);
        $this->em()->persist(new Assignation(Ids::uuid4(), 'ACME0001', $org['organization_id'], Ids::uuid4(), 'Survey', 'default', $this->clock()->now()));
        $this->em()->flush();

        $this->assertApiError($this->api('DELETE', self::URL.'/'.$org['organization_id'], as: $owner), 409, 'ORGANIZATION_HAS_ASSIGNATIONS', 'D2: block the delete rather than orphan its assignations');
        self::assertCount(1, $this->api('GET', self::URL, as: $owner)['json']['organizations']);
    }

    public function testAnOrganizationWithAProjectCannotBeDeleted(): void
    {
        $owner = $this->account('ACME0001');
        $org = $this->data($this->api('POST', self::URL, ['name' => 'Acme'], as: $owner), 201);
        $this->em()->persist(new Project(Ids::uuid4(), 'ACME0001', $org['organization_id'], 'Q3', '2026-12-31', $this->clock()->now()));
        $this->em()->flush();

        $this->assertApiError($this->api('DELETE', self::URL.'/'.$org['organization_id'], as: $owner), 409, 'ORGANIZATION_HAS_ASSIGNATIONS', 'D2: a project keeps its organization (immutable, PRD §6.16)');
    }

    private function eventCount(string $type, string $customerId): int
    {
        return \count($this->em()->getRepository(DomainEventRecord::class)->findBy(['eventType' => $type, 'customerId' => $customerId]));
    }

    private function listingQueryCount(string $as): int
    {
        $this->em()->clear();
        QueryCounter::$count = 0;
        $response = $this->api('GET', self::URL, as: $as);
        self::assertSame(200, $response['status']);

        return QueryCounter::$count;
    }
}
