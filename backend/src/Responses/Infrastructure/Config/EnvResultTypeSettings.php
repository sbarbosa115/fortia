<?php

namespace App\Responses\Infrastructure\Config;

use App\Responses\Application\Port\ResultTypeSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class EnvResultTypeSettings implements ResultTypeSettings
{
    public function __construct(
        #[Autowire(env: 'SAMURAI8_CUSTOMER_IDS')]
        private readonly string $samurai8CustomerIds,
        #[Autowire(env: 'SAMURAI8_QUESTIONNAIRE_IDS')]
        private readonly string $samurai8QuestionnaireIds,
        #[Autowire(env: 'LIVINGOOD_CUSTOMER_IDS')]
        private readonly string $livingoodCustomerIds,
    ) {
    }

    public function samurai8CustomerIds(): array
    {
        return self::list($this->samurai8CustomerIds);
    }

    public function samurai8QuestionnaireIds(): array
    {
        return self::list($this->samurai8QuestionnaireIds);
    }

    public function livingoodCustomerIds(): array
    {
        return self::list($this->livingoodCustomerIds);
    }

    /** @return list<string> */
    private static function list(string $csv): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $csv)), static fn (string $v): bool => '' !== $v));
    }
}
