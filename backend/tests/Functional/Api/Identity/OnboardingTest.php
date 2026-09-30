<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Identity;

use App\Identity\Domain\Model\Customer;
use App\Questionnaires\Domain\Model\Questionnaire;
use App\Shared\Domain\Ids;
use App\Tests\Support\ApiTestCase;

final class OnboardingTest extends ApiTestCase
{
    public function testTheConsoleSetsTheFlagExplicitly(): void
    {
        $email = $this->account('NEWCO001', onboarding: false);
        self::assertFalse($this->data($this->api('GET', '/api/v1/customer/onboarding', as: $email))['onboarding_completed']);

        $this->data($this->api('PATCH', '/api/v1/customer/onboarding', ['completed' => true], as: $email));

        self::assertTrue($this->data($this->api('GET', '/api/v1/customer/onboarding', as: $email))['onboarding_completed']);
    }

    public function testALegacyAccountDerivesItFromHavingAQuestionnaireAndStoresIt(): void
    {
        $email = $this->account('OLD00001');
        $customer = $this->em()->find(Customer::class, 'OLD00001');
        self::assertNotNull($customer);
        $this->em()->getConnection()->executeStatement('UPDATE customer SET onboarding_completed = NULL WHERE customer_id = ?', ['OLD00001']);
        $this->em()->persist(new Questionnaire(Ids::uuid4(), 'OLD00001', 'An old one', 'default', [], new \DateTimeImmutable()));
        $this->em()->flush();
        $this->em()->clear();

        self::assertTrue($this->data($this->api('GET', '/api/v1/customer/onboarding', as: $email))['onboarding_completed'], 'PRD §7.15: a legacy account with a questionnaire has onboarded');
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT onboarding_completed FROM customer WHERE customer_id = ?', ['OLD00001']), 'and the derived value is stored');
    }
}
