<?php

namespace App\Tests\Functional\Api\Questionnaires;

use App\Questionnaires\Application\Command\CopyQuestionnaire;
use App\Responses\Domain\Model\QuestionnaireSession;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Ids;
use App\Tests\Support\ApiTestCase;

/** PRD §8.4 POST /questionnaire/{id}/copy and §7.5 "Copy". */
final class CopyTest extends ApiTestCase
{
    use FlowPayloads;

    public function testACopyIsANewQuestionnaireTitledAndSluggedInTheAccountsLanguage(): void
    {
        $owner = $this->account('ACME0001', language: 'es-CO');
        $id = $this->createQuestionnaire($owner, self::regularFlow('Survey', 'survey'));

        $copy = $this->data($this->api('POST', "/api/v1/questionnaire/$id/copy", as: $owner), 201);
        $again = $this->data($this->api('POST', "/api/v1/questionnaire/$id/copy", as: $owner), 201);

        self::assertNotSame($id, $copy['questionnaire_id']);
        self::assertSame('(copia) Survey', $copy['title']);
        self::assertSame('survey-copia', $copy['slug']);
        self::assertSame('(copia - 2) Survey', $again['title'], 'PRD §7.5: "(copia - N) X" when it exists');
        self::assertSame('survey-copia-2', $again['slug']);
        self::assertSame(1, $copy['question_count']);
        self::assertTrue($copy['is_active']);
        $flow = $this->data($this->api('GET', '/api/v1/flow/survey-copia'));
        self::assertSame($copy['questionnaire_id'], $flow['questionnaire_id']);
        self::assertSame(['questionnaire_id' => $copy['questionnaire_id']], $flow['states'][0]['parameters']);
    }

    public function testAnEnglishAccountGetsEnglishPrefixes(): void
    {
        $owner = $this->account('GLOBEX01', language: 'en-US');
        $id = $this->createQuestionnaire($owner, self::regularFlow('Survey', 'globex-survey'));

        $copy = $this->data($this->api('POST', "/api/v1/questionnaire/$id/copy", as: $owner), 201);

        self::assertSame('(copy) Survey', $copy['title'], 'D23: the prefix follows the account\'s language');
        self::assertSame('globex-survey-copy', $copy['slug']);
    }

    public function testTheDiagnosticAndThePromptsAreCopied(): void
    {
        $owner = $this->account('ACME0001');
        $diagnostic = $this->createQuestionnaire($owner, self::diagnosticFlow());
        $chain = $this->createQuestionnaire($owner, self::chainFlow('Chain', 'Dig into their goals.'));

        $diagnosticCopy = $this->data($this->api('POST', "/api/v1/questionnaire/$diagnostic/copy", as: $owner), 201);
        $chainCopy = $this->data($this->api('POST', "/api/v1/questionnaire/$chain/copy", as: $owner), 201);

        self::assertSame(['t1', 't2'], array_column($diagnosticCopy['on_completed']['tiers'], 'id'));
        $original = $this->data($this->api('GET', "/api/v1/questionnaire/$chain/prompts", as: $owner))['prompts'][0];
        $copied = $this->data($this->api('GET', "/api/v1/questionnaire/{$chainCopy['questionnaire_id']}/prompts", as: $owner))['prompts'][0];
        self::assertSame('Dig into their goals.', $copied['text']);
        self::assertNotSame($original['s3_path'], $copied['s3_path'], 'the copy owns its own prompt text');
        self::assertSame($chainCopy['questionnaire_id'], $copied['questionnaire_id']);
    }

    public function testAnAnsweredQuestionnaireCanBeCopiedButNotEdited(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->createQuestionnaire($owner, self::regularFlow('Answered'));
        $questions = $this->data($this->api('GET', "/api/v1/questionnaire/$id", as: $owner))['questions'];
        $questions[0]['options'][0]['value'] = 'good';
        $this->em()->persist(new QuestionnaireSession(Ids::uuid4(), $id, 'ACME0001', ['questions' => $questions], new \DateTimeImmutable()));
        $this->em()->flush();

        $copy = $this->data($this->api('POST', "/api/v1/questionnaire/$id/copy", as: $owner), 201);

        self::assertSame(200, $this->api('PUT', '/api/v1/questionnaire', ['questionnaire_id' => $copy['questionnaire_id']] + self::regularFlow('Editable copy'), as: $owner)['status'], 'PRD §10.7: the copy has no answers, ready to edit');
    }

    public function testOnlyTheAdminGroupsMayCopy(): void
    {
        $owner = $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);
        $id = $this->createQuestionnaire($owner, self::regularFlow());

        $this->assertApiError($this->api('POST', "/api/v1/questionnaire/$id/copy", as: 'reader@acme.test'), 403, 'FORBIDDEN');
    }

    public function testOtherContextsCopyThroughTheCommand(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->createQuestionnaire($owner, self::regularFlow('Assigned elsewhere'));

        $copyId = static::getContainer()->get(CommandBus::class)->dispatch(new CopyQuestionnaire($id));

        self::assertIsString($copyId);
        self::assertSame('(copia) Assigned elsewhere', $this->data($this->api('GET', "/api/v1/questionnaire/$copyId", as: $owner))['title']);
    }
}
