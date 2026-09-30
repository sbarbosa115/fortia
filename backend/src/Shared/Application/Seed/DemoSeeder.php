<?php

declare(strict_types=1);

namespace App\Shared\Application\Seed;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Seeds one context's demo data for `bin/console app:seed-demo` (dev and the regression suite, never production).
 * A context adds its own seeder in Infrastructure/Seed; seeders run by descending priority and must be idempotent
 * (skip what already exists). The demo accounts are in DemoAccounts.
 */
#[AutoconfigureTag('app.demo_seeder')]
interface DemoSeeder
{
    /** Higher runs first: catalog 100, accounts 90, plans 80, then each context's data ≤ 50. */
    public static function priority(): int;

    public function seed(): void;
}
