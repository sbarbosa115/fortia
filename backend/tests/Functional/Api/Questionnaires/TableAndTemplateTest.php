<?php

namespace App\Tests\Functional\Api\Questionnaires;

use App\Shared\Application\Storage\ObjectStorage;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * A table question (columns, fixed rows, the respondent's rows) and a file question's template: uploaded in the
 * console, or sent as text and stored by the save; downloaded by the respondent.
 */
final class TableAndTemplateTest extends ApiTestCase
{
    use FlowPayloads;

    private const UUID = '0f8fad5b-d9cb-469f-a165-70867728950e';

    public function testATableAndAFileTemplateAreSavedWithTheQuestionnaire(): void
    {
        $owner = $this->account('ACME0001');

        $id = $this->createQuestionnaire($owner, self::flowWith([
            self::table(),
            ['title' => 'Upload your budget', 'options' => [['type' => 'file', 'template' => ['filename' => 'budget.csv', 'text' => "Item,Cost\n"]]]],
        ]));

        $questions = $this->data($this->api('GET', "/api/v1/questionnaire/$id", as: $owner))['questions'];
        $table = $questions[0]['options'][0];
        self::assertSame('table', $table['type']);
        self::assertSame(['Name', 'Role'], array_column($table['options'], 'label'), 'the columns are the control\'s options');
        self::assertNull($table['rows'], 'no fixed rows: the respondent adds them');
        $template = $questions[1]['options'][0]['template'];
        self::assertMatchesRegularExpression('#^templates/ACME0001/[0-9a-f-]{36}/budget\.csv$#', $template['key'], 'a template sent as text is stored under the account');
        self::assertSame('budget.csv', $template['filename']);
        self::assertSame("Item,Cost\n", static::getContainer()->get(ObjectStorage::class)->get($template['key']));
    }

    public function testATableWithoutColumnsOrAnotherAccountsTemplateIsRefused(): void
    {
        $owner = $this->account('ACME0001');
        $this->account('GLOBEX01');

        $this->assertApiError($this->api('POST', '/api/v1/questionnaire', self::flowWith([['title' => 'Team', 'options' => [['type' => 'table', 'options' => []]]]]), as: $owner), 400, 'VALIDATION_ERROR', 'a table needs at least one column');
        $theirs = ['title' => 'Budget', 'options' => [['type' => 'file', 'template' => ['key' => 'templates/GLOBEX01/'.self::UUID.'/theirs.csv', 'filename' => 'theirs.csv']]]];
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire', self::flowWith([$theirs]), as: $owner), 400, 'VALIDATION_ERROR', 'a template must be one of the account\'s uploads');
    }

    public function testTheRespondentAnswersATableWithTheTablesColumnsOnly(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->createQuestionnaire($owner, self::flowWith([self::table()]));
        $session = $this->bare($this->api('POST', "/api/v1/questionnaire/$id/session"));

        $session['questions'][0]['options'][0]['value'] = [['name' => 'Ana', 'role' => 'CEO', 'salary' => '1M'], ['name' => '', 'role' => '']];
        $saved = $this->data($this->api('PUT', '/api/v1/questionnaire/session', $session));

        self::assertSame([['name' => 'Ana', 'role' => 'CEO']], $saved['questions'][0]['options'][0]['value'], 'unknown columns and empty rows are dropped');
    }

    public function testATemplateIsUploadedByTheAccountsUsersAndDownloadedByAnyone(): void
    {
        $this->account('ACME0001');
        $this->account('GLOBEX01');
        $body = ['filename' => 'Presupuesto 2026.xlsx', 'content_type' => 'application/vnd.ms-excel', 'customer_id' => 'ACME0001', 'upload_type' => 'template'];

        $upload = $this->data($this->api('POST', '/api/v1/signed-urls', $body, as: 'root@acme0001.test'));

        self::assertMatchesRegularExpression('#^templates/ACME0001/[0-9a-f-]{36}/Presupuesto 2026\.xlsx$#', $upload['key'], 'the key keeps the file\'s name');
        self::assertSame((string) (20 * 1024 * 1024), $upload['fields']['max'], 'a template is at most 20 MB');
        $this->assertApiError($this->api('POST', '/api/v1/signed-urls', $body), 401, 'UNAUTHORIZED');
        $this->assertApiError($this->api('POST', '/api/v1/signed-urls', $body, as: 'root@globex01.test'), 403, 'FORBIDDEN', 'another account\'s template');

        $file = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($file, "Item,Cost\n");
        $this->client->request('POST', $upload['url'], $upload['fields'], ['file' => new UploadedFile($file, 'Presupuesto 2026.xlsx', 'application/vnd.ms-excel', null, true)]);
        self::assertSame(204, $this->client->getResponse()->getStatusCode(), 'the signed form uploads the template: '.$this->client->getResponse()->getContent());
        self::assertSame("Item,Cost\n", static::getContainer()->get(ObjectStorage::class)->get($upload['key']));

        $key = 'templates/ACME0001/'.self::UUID.'/budget.csv';
        static::getContainer()->get(ObjectStorage::class)->put($key, "Item,Cost\n", 'text/csv');
        $download = $this->data($this->api('POST', '/api/v1/templates/download-urls', ['key' => $key]));
        self::assertStringContainsString('/storage/download?', $download['url'], 'a respondent downloads it without signing in');
        self::assertSame(900, $download['expires_in']);
        $this->assertApiError($this->api('POST', '/api/v1/templates/download-urls', ['key' => 'templates/ACME0001/'.self::UUID.'/missing.csv']), 404, 'TEMPLATE_NOT_FOUND');
        $this->assertApiError($this->api('POST', '/api/v1/templates/download-urls', ['key' => 'ACME0001/s/q/answer.pdf']), 404, 'TEMPLATE_NOT_FOUND', 'only templates, never an answer\'s file');
    }

    /**
     * @param list<array<string, mixed>> $questions
     *
     * @return array<string, mixed>
     */
    private static function flowWith(array $questions): array
    {
        $flow = self::regularFlow('Team plan');
        $flow['states'][0]['parameters']['questionnaire']['questions'] = $questions;

        return $flow;
    }

    /** @return array<string, mixed> */
    private static function table(): array
    {
        return ['title' => 'Who is on your team?', 'options' => [['type' => 'table', 'options' => [['label' => 'Name', 'value' => 'name'], ['label' => 'Role', 'value' => 'role']]]]];
    }

    /**
     * @param array{status: int, json: mixed, body: string} $response
     *
     * @return array<string, mixed>
     */
    private function bare(array $response): array
    {
        self::assertSame(200, $response['status'], $response['body']);
        self::assertIsArray($response['json']);

        return $response['json'];
    }
}
