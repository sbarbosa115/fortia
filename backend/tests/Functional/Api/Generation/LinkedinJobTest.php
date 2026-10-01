<?php

namespace App\Tests\Functional\Api\Generation;

use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Tests\Support\ApiTestCase;

/** A diagnostic generated from a LinkedIn profile (PRD §7.18, §8.4 POST /questionnaire/linkedin, D8). */
final class LinkedinJobTest extends ApiTestCase
{
    /** .env's LINKEDIN_OWNER_CUSTOMER_ID (D8: the owner is configuration). */
    private const OWNER = 'XhEFtqTt';

    public function testAProfileBecomesADiagnosticOwnedByTheConfiguredAccount(): void
    {
        $this->account(self::OWNER);

        $response = $this->api('POST', '/api/v1/questionnaire/linkedin', ['linkedin_url' => 'https://www.linkedin.com/in/ana-perez', 'language' => 'en']);

        $job = $this->data($response, 202)['job'];
        self::assertSame('linkedin_questionnaire', $job['job_type'], '§8.4: 202 {job}');
        $job = $this->job($job['job_id']);
        self::assertSame('COMPLETED', $job['status'], 'body: '.json_encode($job));
        self::assertSame('linkedin_questionnaire', $job['result']['type']);

        $queries = static::getContainer()->get(QuestionnaireQueries::class);
        $questionnaire = $queries->find($job['result']['questionnaire_id']);
        self::assertNotNull($questionnaire);
        self::assertSame(self::OWNER, $questionnaire->customerId(), '§7.18, D8: owned by the configured account');
        self::assertSame('diagnostic', $questionnaire->type());
        self::assertSame('Leadership diagnostic for Ana Perez', $questionnaire->title(), 'in the requested language, from the profile');
        self::assertCount(8, $questionnaire->questions());
        self::assertNotNull($queries->flowOf($questionnaire->id()), 'it has a public flow (a link that works)');

        $diagnostic = $queries->diagnosticOf($questionnaire->id());
        self::assertNotNull($diagnostic);
        self::assertSame([[0, 7], [8, 15], [16, 24]], array_map(static fn (array $t): array => [$t['min'], $t['max']], $diagnostic['tiers']), '§7.8: tier bands computed on the server (8 questions × 3 = 24)');

        $request = $this->llm()->requests()[0];
        self::assertSame('linkedin--rules-to-create-diagnostic-questionnaires', $request->purpose);
        self::assertStringContainsString('<profile>', $request->messages[0]->content, 'the profile is untrusted data, between its tags');
        self::assertStringContainsString('Write every text in English', $request->messages[0]->content);
    }

    public function testAnyLanguageOtherThanEnglishGivesASpanishQuestionnaire(): void
    {
        $this->account(self::OWNER);

        $job = $this->generate(['linkedin_url' => 'https://co.linkedin.com/in/luis-gomez/', 'language' => 'fr']);

        $questionnaire = static::getContainer()->get(QuestionnaireQueries::class)->find($job['result']['questionnaire_id']);
        self::assertSame('Diagnóstico de liderazgo para Luis Gomez', $questionnaire?->title(), '§8.4: any other value becomes es');
    }

    public function testAProfileThatCannotBeReadFailsTheJobAndStoresNothing(): void
    {
        $this->account(self::OWNER);

        $job = $this->generate(['linkedin_url' => 'https://www.linkedin.com/in/unavailable', 'language' => 'es']);

        self::assertSame('FAILED', $job['status']);
        self::assertSame('LINKEDIN_PROFILE_UNAVAILABLE', $job['result']['error']['type'] ?? null, 'body: '.json_encode($job));
        self::assertSame(0, static::getContainer()->get(QuestionnaireQueries::class)->countRootsOf(self::OWNER));
        self::assertSame([], $this->llm()->requests(), 'the model is not called without a profile');
    }

    public function testOnlyALinkedinProfileUrlIsAccepted(): void
    {
        foreach (['https://www.linkedin.com/company/acme', 'https://evil.test/in/ana', 'not a url'] as $url) {
            $this->assertApiError($this->api('POST', '/api/v1/questionnaire/linkedin', ['linkedin_url' => $url, 'language' => 'es']), 400, 'VALIDATION_ERROR', "§8.4: $url is not a profile");
        }
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire/linkedin', ['language' => 'es']), 400, 'VALIDATION_ERROR', 'linkedin_url is required');
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire/linkedin', ['linkedin_url' => 'https://www.linkedin.com/in/ana', 'owner' => 'GLOBEX01']), 400, 'VALIDATION_ERROR', 'no extra fields: the owner cannot be chosen by the caller');
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed> the finished job
     */
    private function generate(array $body): array
    {
        return $this->job($this->data($this->api('POST', '/api/v1/questionnaire/linkedin', $body), 202)['job']['job_id']);
    }

    /** @return array<string, mixed> */
    private function job(string $jobId): array
    {
        return $this->data($this->api('GET', '/api/v1/jobs/'.$jobId))['job'];
    }
}
