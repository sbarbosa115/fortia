<?php

namespace App\Tests\Unit\Shared\Llm;

use App\Shared\Application\Llm\LlmAttachment;
use App\Shared\Application\Llm\LlmMessage;
use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmTool;
use App\Shared\Application\Llm\LlmToolCall;
use App\Shared\Application\Llm\LlmToolResult;
use App\Shared\Application\Llm\LlmUnavailable;
use App\Shared\Application\Llm\OpenAiKeys;
use App\Shared\Infrastructure\Llm\OpenAiLanguageModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OpenAiLanguageModelTest extends TestCase
{
    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $calls = [];

    /** @param list<array<string, mixed>|MockResponse> $answers */
    private function model(array $answers, string $model = 'gpt-5.6-terra'): OpenAiLanguageModel
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$answers): MockResponse {
            $this->calls[] = ['method' => $method, 'url' => $url, 'options' => $options];
            $answer = array_shift($answers);

            return $answer instanceof MockResponse ? $answer : new MockResponse((string) json_encode($answer));
        });
        $keys = new class implements OpenAiKeys {
            public function keyFor(?string $customerId): string
            {
                return match ($customerId) {
                    'ACME0001' => 'sk-acme',
                    'NOKEY001' => '',
                    default => 'sk-platform',
                };
            }
        };

        return new OpenAiLanguageModel($http, $keys, $model);
    }

    /** @return array<string, mixed> */
    private function sentBody(int $call = 0): array
    {
        return json_decode((string) $this->calls[$call]['options']['body'], true);
    }

    /**
     * @param list<array<string, mixed>> $output
     *
     * @return array<string, mixed>
     */
    private static function answer(array $output, string $status = 'completed'): array
    {
        return ['id' => 'resp_1', 'status' => $status, 'output' => $output];
    }

    /** @return array<string, mixed> */
    private static function text(string $text): array
    {
        return ['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => $text]]];
    }

    public function testItCallsTheResponsesApiWithTheConfiguredModelAndTheAccountsKey(): void
    {
        $model = $this->model([self::answer([self::text('Hello')])]);

        $response = $model->complete(LlmRequest::single('greeting', 'Be brief.', 'Hi', customerId: 'ACME0001'));

        self::assertSame('Hello', $response->text);
        self::assertSame('POST', $this->calls[0]['method']);
        self::assertSame('https://api.openai.com/v1/responses', $this->calls[0]['url']);
        self::assertContains('Authorization: Bearer sk-acme', $this->calls[0]['options']['headers'], 'the account\'s own key');
        $body = $this->sentBody();
        self::assertSame('gpt-5.6-terra', $body['model'], 'one model for the whole app (LLM_MODEL)');
        self::assertSame('Be brief.', $body['instructions']);
        self::assertSame([['role' => 'user', 'content' => [['type' => 'input_text', 'text' => 'Hi']]]], $body['input']);
        self::assertSame(16000, $body['max_output_tokens']);
        self::assertSame(['effort' => 'medium'], $body['reasoning'], 'generation work reasons at medium effort');
        self::assertFalse($body['store'], 'nothing is kept on OpenAI\'s side');
    }

    public function testTheFastTierReasonsLessAndARequestWithoutAccountUsesThePlatformKey(): void
    {
        $model = $this->model([self::answer([self::text('ok')])]);

        $model->complete(LlmRequest::single('evaluate', '', 'x', null, LlmRequest::TIER_FAST));

        self::assertSame(['effort' => 'low'], $this->sentBody()['reasoning']);
        self::assertArrayNotHasKey('instructions', $this->sentBody(), 'no empty system prompt');
        self::assertContains('Authorization: Bearer sk-platform', $this->calls[0]['options']['headers']);
    }

    public function testWithoutAnyKeyTheModelIsUnavailable(): void
    {
        $this->expectException(LlmUnavailable::class);
        $this->model([])->complete(LlmRequest::single('x', '', 'x', customerId: 'NOKEY001'));
    }

    public function testStructuredOutputSendsTheSchemaAndReturnsTheParsedJson(): void
    {
        $schema = ['type' => 'object', 'properties' => ['title' => ['type' => 'string']], 'required' => ['title']];
        $model = $this->model([self::answer([['type' => 'reasoning', 'summary' => []], self::text('{"title":"Survey"}')])]);

        $response = $model->complete(LlmRequest::single('followups--rules', 'sys', 'user', $schema));

        self::assertSame(['title' => 'Survey'], $response->json);
        self::assertSame(['format' => ['type' => 'json_schema', 'name' => 'followups--rules', 'schema' => $schema, 'strict' => false]], $this->sentBody()['text']);
    }

    public function testInvalidJsonForASchemaIsUnavailable(): void
    {
        $this->expectException(LlmUnavailable::class);
        $this->model([self::answer([self::text('not json')])])->complete(LlmRequest::single('x', '', 'x', ['type' => 'object']));
    }

    public function testToolsAreOfferedAndTheirCallsReturned(): void
    {
        $model = $this->model([self::answer([['type' => 'function_call', 'call_id' => 'call_1', 'name' => 'list_questionnaires', 'arguments' => '{"q":"sales"}']])]);
        $tool = new LlmTool('list_questionnaires', 'Lists them', ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]]);

        $response = $model->complete(new LlmRequest('chat', 'sys', [LlmMessage::user('find sales')], tools: [$tool]));

        self::assertTrue($response->wantsTools());
        self::assertEquals([new LlmToolCall('call_1', 'list_questionnaires', ['q' => 'sales'])], $response->toolCalls);
        self::assertSame([['type' => 'function', 'name' => 'list_questionnaires', 'description' => 'Lists them', 'parameters' => $tool->inputSchema, 'strict' => false]], $this->sentBody()['tools']);
    }

    public function testAConversationWithToolCallsAndResultsBecomesResponsesItems(): void
    {
        $model = $this->model([self::answer([self::text('Done')])]);
        $messages = [
            LlmMessage::user('find sales'),
            new LlmMessage('assistant', 'Looking.', [new LlmToolCall('call_1', 'list_questionnaires', ['q' => 'sales'])]),
            LlmMessage::toolResults([new LlmToolResult('call_1', '[]', true)]),
        ];

        $model->complete(new LlmRequest('chat', 'sys', $messages));

        self::assertSame([
            ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => 'find sales']]],
            ['role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => 'Looking.']]],
            ['type' => 'function_call', 'call_id' => 'call_1', 'name' => 'list_questionnaires', 'arguments' => '{"q":"sales"}'],
            ['type' => 'function_call_output', 'call_id' => 'call_1', 'output' => 'Error: []'],
        ], $this->sentBody()['input']);
    }

    public function testAttachmentsGoWithTheFirstUserMessage(): void
    {
        $model = $this->model([self::answer([self::text('ok')])]);
        $files = [new LlmAttachment('brief.pdf', 'application/pdf', '%PDF'), new LlmAttachment('logo.png', 'image/png', 'PNG'), new LlmAttachment('notes.txt', 'text/plain', 'some notes')];

        $model->complete(new LlmRequest('generate', 'sys', [LlmMessage::user('Use these')], attachments: $files));

        self::assertSame([
            ['type' => 'input_file', 'filename' => 'brief.pdf', 'file_data' => 'data:application/pdf;base64,'.base64_encode('%PDF')],
            ['type' => 'input_image', 'image_url' => 'data:image/png;base64,'.base64_encode('PNG')],
            ['type' => 'input_text', 'text' => "<document name=\"notes.txt\">\nsome notes\n</document>"],
            ['type' => 'input_text', 'text' => 'Use these'],
        ], $this->sentBody()['input'][0]['content']);
    }

    public function testARefusalAnOutOfTokensAnswerAndAnHttpErrorAreUnavailable(): void
    {
        $cases = [
            'refusal' => self::answer([['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'refusal', 'refusal' => 'No.']]]]),
            'out of tokens' => self::answer([], 'incomplete') + ['incomplete_details' => ['reason' => 'max_output_tokens']],
            'http error' => new MockResponse('{"error":{"message":"Incorrect API key"}}', ['http_code' => 401]),
        ];
        foreach ($cases as $why => $answer) {
            try {
                $this->model([$answer])->complete(LlmRequest::single('x', '', 'x'));
                self::fail($why.' should be unavailable');
            } catch (LlmUnavailable $e) {
                self::assertStringNotContainsString('sk-', $e->getMessage(), 'the key never leaks into errors');
            }
        }
    }

    public function testARequestCanStillAskForAnotherModel(): void
    {
        $this->model([self::answer([self::text('ok')])])->complete(new LlmRequest('x', '', [LlmMessage::user('x')], model: 'gpt-5.6-sol'));

        self::assertSame('gpt-5.6-sol', $this->sentBody()['model']);
    }
}
