<?php

namespace App\Tests\Unit\Organizations;

use App\Organizations\Domain\MemberReconciliation;
use App\Organizations\Domain\Model\MemberDraft;
use App\Organizations\Domain\Model\OrganizationUser;
use PHPUnit\Framework\TestCase;

/**
 * PRD §8.7 PUT /organizations/{id}: the members sent are reconciled with the stored ones — matched by id, then by
 * email, then by name + phone; stored members that match nothing are deleted.
 */
final class MemberReconciliationTest extends TestCase
{
    private const ORG = '11111111-1111-4111-8111-111111111111';

    public function testAMemberSentWithItsIdIsUpdated(): void
    {
        $ana = $this->member('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'Ana', 'ana@acme.test');

        $plan = MemberReconciliation::plan([$ana], [MemberDraft::of(['organization_user_id' => $ana->organizationUserId(), 'name' => 'Ana Renamed', 'email' => 'ana.new@acme.test'])]);

        self::assertCount(1, $plan->updates, 'matched by id');
        self::assertSame($ana, $plan->updates[0][0]);
        self::assertSame([], $plan->creates);
        self::assertSame([], $plan->removals);
    }

    public function testWithoutAnIdTheEmailMatches(): void
    {
        $ana = $this->member('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'Ana', 'ana@acme.test');

        $plan = MemberReconciliation::plan([$ana], [MemberDraft::of(['name' => 'Ana María', 'email' => 'ANA@acme.test'])]);

        self::assertSame($ana, $plan->updates[0][0] ?? null, 'matched by email when no id is sent');
        self::assertSame([], $plan->removals);
    }

    public function testWithoutIdOrEmailTheNameAndPhoneMatch(): void
    {
        $bruno = $this->member('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Bruno Díaz', null, '+57300');

        $plan = MemberReconciliation::plan([$bruno], [MemberDraft::of(['name' => 'BRUNO  diaz', 'phone' => '+57 300', 'role' => 'Lead'])]);

        self::assertSame($bruno, $plan->updates[0][0] ?? null, 'matched by name + phone (both normalized)');
    }

    public function testTheSameNameWithAnotherPhoneIsANewMember(): void
    {
        $bruno = $this->member('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Bruno', null, '+57300');

        $plan = MemberReconciliation::plan([$bruno], [MemberDraft::of(['name' => 'Bruno', 'phone' => '+57999'])]);

        self::assertCount(1, $plan->creates);
        self::assertSame([$bruno], $plan->removals, 'a stored member that matches nothing is deleted');
    }

    public function testStoredMembersThatAreNotSentAreDeleted(): void
    {
        $ana = $this->member('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'Ana', 'ana@acme.test');
        $carla = $this->member('cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'Carla', 'carla@acme.test');

        $plan = MemberReconciliation::plan([$ana, $carla], [MemberDraft::of(['name' => 'Ana', 'email' => 'ana@acme.test'])]);

        self::assertSame([$carla], $plan->removals);
    }

    public function testAnIdMatchWinsOverAnEmailMatchOfAnEarlierMember(): void
    {
        $ana = $this->member('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'Ana', 'ana@acme.test');

        // The first one sent has Ana's email but no id; the second one names Ana by id: the id match comes first.
        $plan = MemberReconciliation::plan([$ana], [
            MemberDraft::of(['name' => 'Someone', 'email' => 'ana@acme.test']),
            MemberDraft::of(['organization_user_id' => $ana->organizationUserId(), 'name' => 'Ana', 'email' => 'ana.b@acme.test']),
        ]);

        self::assertSame($ana, $plan->updates[0][0] ?? null);
        self::assertSame('ana.b@acme.test', $plan->updates[0][1]->email ?? null, 'Ana is matched by her id, not by the email of another row');
        self::assertCount(1, $plan->creates);
    }

    public function testAnUnknownIdIsTreatedAsANewMember(): void
    {
        $plan = MemberReconciliation::plan([], [MemberDraft::of(['organization_user_id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'name' => 'Dana', 'email' => 'dana@acme.test'])]);

        self::assertCount(1, $plan->creates, 'an id that is not one of this organization\'s members creates a new member');
    }

    public function testAStoredMemberIsMatchedOnlyOnce(): void
    {
        $ana = $this->member('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'Ana', null, '+1555');

        $plan = MemberReconciliation::plan([$ana], [
            MemberDraft::of(['name' => 'Ana', 'phone' => '+1555']),
            MemberDraft::of(['name' => 'Ana', 'phone' => '+1555', 'email' => 'x@acme.test']),
        ]);

        self::assertCount(1, $plan->updates);
        self::assertCount(1, $plan->creates);
    }

    private function member(string $id, string $name, ?string $email, ?string $phone = null): OrganizationUser
    {
        return new OrganizationUser($id, self::ORG, $name, $email, $phone, null, null, new \DateTimeImmutable('2026-01-01'));
    }
}
