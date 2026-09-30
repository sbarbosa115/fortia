<?php

namespace App\Organizations\Infrastructure\Seed;

use App\Organizations\Domain\Model\Organization;
use App\Organizations\Domain\Model\OrganizationUser;
use App\Organizations\Domain\Repository\OrganizationRepository;
use App\Organizations\Domain\Repository\OrganizationUserRepository;
use App\Shared\Application\Seed\DemoAccounts;
use App\Shared\Application\Seed\DemoSeeder;
use App\Shared\Domain\Clock;

/**
 * Demo organizations for the regression suite (docs/tests/ui-regression.md §7): two for Acme (one with a domain and
 * four members, one inactive without a domain) and one for Globex, which Acme must never see. Fixed ids, so other
 * seeders (assignations) can point at them.
 */
final class DemoOrganizationsSeeder implements DemoSeeder
{
    public const ACME_RETAIL = '0a9f3c1e-5b7d-4e2a-9c10-000000000001';
    public const ACME_LOGISTICS = '0a9f3c1e-5b7d-4e2a-9c10-000000000002';
    public const GLOBEX_LABS = '0a9f3c1e-5b7d-4e2a-9c10-000000000003';

    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly OrganizationUserRepository $members,
        private readonly Clock $clock,
    ) {
    }

    public static function priority(): int
    {
        return 50;
    }

    public function seed(): void
    {
        $this->organization(self::ACME_RETAIL, DemoAccounts::ACME, 'Acme Retail', 'acme-retail.test', 'Stores and e-commerce team.', true, [
            ['María Gómez', 'maria@acme-retail.test', '+573001112233', 'Store manager', 'Sales'],
            ['Juan Pérez', 'juan@acme-retail.test', null, 'Cashier', 'Sales'],
            ['Lucía Fernández', 'lucia@acme-retail.test', '+573004445566', 'Analyst', 'Operations'],
            ['Pedro Ruiz', null, '+573007778899', 'Driver', 'Logistics'],
        ]);
        $this->organization(self::ACME_LOGISTICS, DemoAccounts::ACME, 'Acme Logistics', null, null, false, [
            ['Sofía Castro', 'sofia@acme-logistics.test', null, 'Coordinator', 'Warehouse'],
            ['Diego Mora', 'diego@partner.test', null, null, null],
        ]);
        $this->organization(self::GLOBEX_LABS, DemoAccounts::GLOBEX, 'Globex Labs', 'globex.test', 'Research team.', true, [
            ['Hank Scorpio', 'hank@globex.test', null, 'CEO', 'Management'],
        ]);
    }

    /** @param list<array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string}> $members */
    private function organization(string $id, string $customerId, string $name, ?string $domain, ?string $description, bool $active, array $members): void
    {
        if (null !== $this->organizations->find($id)) {
            return;
        }
        $now = $this->clock->now();
        $organization = new Organization($id, $customerId, $name, $now);
        $organization->describe($name, $domain, $description, $active, $now);
        $this->organizations->add($organization);
        foreach ($members as $index => [$memberName, $email, $phone, $role, $area]) {
            // The organization's id with its last block made unique per member: ...-001000000001, ...-001000000002
            $memberId = substr($id, 0, 24).substr($id, -3).str_pad((string) ($index + 1), 9, '0', \STR_PAD_LEFT);
            $this->members->add(new OrganizationUser($memberId, $id, $memberName, $email, $phone, $role, $area, $now));
        }
    }
}
