<?php

namespace App\Responses\Application\Port;

/**
 * Which accounts and questionnaires get a client-specific result (D8), from the environment: SAMURAI8_CUSTOMER_IDS,
 * SAMURAI8_QUESTIONNAIRE_IDS and LIVINGOOD_CUSTOMER_IDS, each a comma-separated list.
 */
interface ResultTypeSettings
{
    /** @return list<string> */
    public function samurai8CustomerIds(): array;

    /** @return list<string> */
    public function samurai8QuestionnaireIds(): array;

    /** @return list<string> */
    public function livingoodCustomerIds(): array;
}
