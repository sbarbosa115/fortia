<?php

declare(strict_types=1);

namespace App\Shared\Application\Seed;

/**
 * The demo accounts every seeder and the regression suite share (docs/tests/ui-regression.md). Dev only: never
 * reuse these passwords anywhere.
 */
final class DemoAccounts
{
    public const PASSWORD = 'password123';

    /** The platform's super-admin (group Admin). */
    public const PLATFORM = 'MAPPI001';
    public const PLATFORM_ADMIN = 'admin@mappi.test';

    /** A paying customer on the "pro" plan, onboarding done. */
    public const ACME = 'ACME0001';
    public const ACME_OWNER = 'owner@acme.test';
    public const ACME_ADMIN = 'admin@acme.test';
    public const ACME_READER = 'reader@acme.test';

    /** Another tenant on the "starter" plan: its data must never show up for Acme. */
    public const GLOBEX = 'GLOBEX01';
    public const GLOBEX_OWNER = 'owner@globex.test';

    /** A brand-new account that has not done onboarding. */
    public const NEWCO = 'NEWCO001';
    public const NEWCO_OWNER = 'owner@newco.test';

    /**
     * @return list<array{customer_id: string, email: string, name: string, root: bool, groups: list<string>, language: string, onboarding: bool}>
     */
    public static function users(): array
    {
        return [
            ['customer_id' => self::PLATFORM, 'email' => self::PLATFORM_ADMIN, 'name' => 'Mappi Admin', 'root' => true, 'groups' => ['Admin'], 'language' => 'en-US', 'onboarding' => true],
            ['customer_id' => self::ACME, 'email' => self::ACME_OWNER, 'name' => 'Ana Owner', 'root' => true, 'groups' => ['Customer-Admin'], 'language' => 'es-CO', 'onboarding' => true],
            ['customer_id' => self::ACME, 'email' => self::ACME_ADMIN, 'name' => 'Andrés Admin', 'root' => false, 'groups' => ['Customer-Admin'], 'language' => 'es-CO', 'onboarding' => true],
            ['customer_id' => self::ACME, 'email' => self::ACME_READER, 'name' => 'Rita Reader', 'root' => false, 'groups' => ['Customer-Read-Only'], 'language' => 'es-CO', 'onboarding' => true],
            ['customer_id' => self::GLOBEX, 'email' => self::GLOBEX_OWNER, 'name' => 'Gael Owner', 'root' => true, 'groups' => ['Customer-Admin'], 'language' => 'en-US', 'onboarding' => true],
            ['customer_id' => self::NEWCO, 'email' => self::NEWCO_OWNER, 'name' => 'Nora New', 'root' => true, 'groups' => ['Customer-Admin'], 'language' => 'en-US', 'onboarding' => false],
        ];
    }

    /** @return array<string, string> the plan of each demo account */
    public static function plans(): array
    {
        return [self::PLATFORM => 'business', self::ACME => 'pro', self::GLOBEX => 'starter', self::NEWCO => 'starter'];
    }
}
