<?php

namespace App\Tests\Unit\Shared\Llm;

use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmResponse;
use App\Shared\Application\Llm\OpenAiKeys;
use App\Shared\Infrastructure\Llm\AutoLanguageModel;
use App\Shared\Infrastructure\Llm\Fake\FakeLanguageModel;
use App\Shared\Infrastructure\Llm\LanguageModelFactory;
use App\Shared\Infrastructure\Llm\OpenAiLanguageModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AutoLanguageModelTest extends TestCase
{
    private int $openAiCalls = 0;

    private function keys(): OpenAiKeys
    {
        return new class implements OpenAiKeys {
            public function keyFor(?string $customerId): string
            {
                return 'ACME0001' === $customerId ? 'sk-acme' : '';
            }
        };
    }

    private function openAi(string $model = 'gpt-5.6-terra'): OpenAiLanguageModel
    {
        $http = new MockHttpClient(function (): MockResponse {
            ++$this->openAiCalls;

            return new MockResponse((string) json_encode(['id' => 'r', 'status' => 'completed', 'output' => [
                ['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => 'from OpenAI']]],
            ]]));
        });

        return new OpenAiLanguageModel($http, $this->keys(), $model);
    }

    private function fake(): FakeLanguageModel
    {
        $fake = new FakeLanguageModel([]);
        $fake->willAnswer(new LlmResponse('from the fake'));

        return $fake;
    }

    public function testAnAccountWithAKeyTalksToOpenAi(): void
    {
        $auto = new AutoLanguageModel($this->openAi(), $this->fake(), $this->keys());

        $response = $auto->complete(LlmRequest::single('greeting', 'Be brief.', 'Hi', customerId: 'ACME0001'));

        self::assertSame('from OpenAI', $response->text, 'LLM_PROVIDER=auto: a key (the account\'s or OPENAI_API_KEY) means the real model');
        self::assertSame(1, $this->openAiCalls);
    }

    public function testWithoutAnyKeyTheOfflineFakeAnswers(): void
    {
        $auto = new AutoLanguageModel($this->openAi(), $this->fake(), $this->keys());

        $response = $auto->complete(LlmRequest::single('greeting', 'Be brief.', 'Hi', customerId: 'NOKEY001'));

        self::assertSame('from the fake', $response->text, 'LLM_PROVIDER=auto: no key anywhere falls back to the fake');
        self::assertSame(0, $this->openAiCalls);
    }

    public function testTheFactoryReadsTheProviderLeniently(): void
    {
        $openAi = $this->openAi();
        $fake = new FakeLanguageModel([]);
        $auto = new AutoLanguageModel($openAi, $fake, $this->keys());
        $factory = new LanguageModelFactory($openAi, $fake, $auto);

        self::assertSame($openAi, $factory->create('openai'));
        self::assertSame($auto, $factory->create(' AUTO '));
        self::assertSame($fake, $factory->create('fake'));
        self::assertSame($fake, $factory->create(''), 'an unknown or empty provider is the offline fake');
    }

    public function testAnEmptyModelFallsBackToLlmModelThenToTheDefault(): void
    {
        self::assertSame('gpt-x', OpenAiLanguageModel::model('gpt-x', 'gpt-5.6-terra'), 'the call\'s own model wins');
        self::assertSame('gpt-5.6-terra', OpenAiLanguageModel::model(null, ' gpt-5.6-terra '));
        self::assertSame(OpenAiLanguageModel::DEFAULT_MODEL, OpenAiLanguageModel::model('', ''), 'an empty LLM_MODEL never reaches OpenAI');
    }
}
