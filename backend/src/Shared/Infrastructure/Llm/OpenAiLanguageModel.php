<?php

namespace App\Shared\Infrastructure\Llm;

use App\Shared\Application\Llm\LanguageModel;
use App\Shared\Application\Llm\LlmAttachment;
use App\Shared\Application\Llm\LlmMessage;
use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmResponse;
use App\Shared\Application\Llm\LlmToolCall;
use App\Shared\Application\Llm\LlmUnavailable;
use App\Shared\Application\Llm\OpenAiKeys;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenAI's Responses API. One model for every call (LLM_MODEL), billed to the account's own key when it saved one
 * (/profile › System), else to the platform's OPENAI_API_KEY. Structured output is text.format json_schema (not
 * strict: our schemas have optional fields); tools are function tools run by the caller's loop (the chat). The tier
 * only sets the reasoning effort. Nothing is stored on OpenAI's side (store: false).
 */
final class OpenAiLanguageModel implements LanguageModel
{
    private const URL = 'https://api.openai.com/v1/responses';
    private const TIMEOUT_SECONDS = 300;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly OpenAiKeys $keys,
        private readonly string $model,
    ) {
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        $key = $this->keys->keyFor($request->customerId);
        if ('' === $key) {
            throw new LlmUnavailable('No OpenAI API key: set one in Profile › System or OPENAI_API_KEY.');
        }

        $body = [
            'model' => $request->model ?? $this->model,
            'input' => $this->input($request),
            'max_output_tokens' => $request->maxTokens,
            'reasoning' => ['effort' => LlmRequest::TIER_FAST === $request->tier ? 'low' : 'medium'],
            'store' => false,
        ];
        if ('' !== $request->system) {
            $body['instructions'] = $request->system;
        }
        if (null !== $request->jsonSchema) {
            $body['text'] = ['format' => ['type' => 'json_schema', 'name' => self::schemaName($request->purpose), 'schema' => $request->jsonSchema, 'strict' => false]];
        }
        if ([] !== $request->tools) {
            $body['tools'] = array_map(static fn ($tool): array => [
                'type' => 'function',
                'name' => $tool->name,
                'description' => $tool->description,
                'parameters' => $tool->inputSchema,
                'strict' => false,
            ], $request->tools);
        }

        try {
            $response = $this->http->request('POST', self::URL, ['auth_bearer' => $key, 'json' => $body, 'timeout' => self::TIMEOUT_SECONDS]);
            $status = $response->getStatusCode();
            /** @var array<string, mixed> $data */
            $data = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new LlmUnavailable('The language model could not be reached: '.$e->getMessage(), 0, $e);
        }
        if ($status >= 400) {
            throw new LlmUnavailable(\sprintf('The language model answered HTTP %d: %s', $status, self::errorMessage($data)));
        }

        return $this->response($request, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function response(LlmRequest $request, array $data): LlmResponse
    {
        if ('incomplete' === ($data['status'] ?? null)) {
            $reason = $data['incomplete_details']['reason'] ?? 'unknown';
            throw new LlmUnavailable('max_output_tokens' === $reason ? 'The language model ran out of output tokens.' : 'The language model stopped early: '.$reason);
        }

        $text = '';
        $calls = [];
        foreach ((array) ($data['output'] ?? []) as $item) {
            if (!\is_array($item)) {
                continue;
            }
            if ('function_call' === ($item['type'] ?? null)) {
                $arguments = json_decode((string) ($item['arguments'] ?? '{}'), true);
                $calls[] = new LlmToolCall((string) ($item['call_id'] ?? ''), (string) ($item['name'] ?? ''), \is_array($arguments) ? $arguments : []);
            } elseif ('message' === ($item['type'] ?? null)) {
                foreach ((array) ($item['content'] ?? []) as $part) {
                    if ('refusal' === ($part['type'] ?? null)) {
                        throw new LlmUnavailable('The language model declined the request.');
                    }
                    if ('output_text' === ($part['type'] ?? null)) {
                        $text .= (string) ($part['text'] ?? '');
                    }
                }
            }
        }
        if ([] !== $calls) {
            return LlmResponse::toolCalls($calls, $text);
        }
        if (null !== $request->jsonSchema) {
            $json = json_decode($text, true);
            if (!\is_array($json)) {
                throw new LlmUnavailable('The language model did not return valid JSON.');
            }

            return new LlmResponse($text, $json);
        }

        return new LlmResponse($text);
    }

    /** @return list<array<string, mixed>> */
    private function input(LlmRequest $request): array
    {
        $items = [];
        $attachmentsPending = [] !== $request->attachments;
        foreach ($request->messages as $message) {
            foreach ($message->toolResults as $result) {
                $items[] = ['type' => 'function_call_output', 'call_id' => $result->toolCallId, 'output' => ($result->isError ? 'Error: ' : '').$result->content];
            }
            $content = [];
            if ('user' === $message->role && $attachmentsPending) {
                foreach ($request->attachments as $attachment) {
                    $content[] = self::attachmentPart($attachment);
                }
                $attachmentsPending = false;
            }
            if ('' !== $message->content) {
                $content[] = ['type' => 'assistant' === $message->role ? 'output_text' : 'input_text', 'text' => $message->content];
            }
            if ([] !== $content) {
                $items[] = ['role' => $message->role, 'content' => $content];
            }
            foreach ($message->toolCalls as $call) {
                $items[] = ['type' => 'function_call', 'call_id' => $call->id, 'name' => $call->name, 'arguments' => (string) json_encode((object) $call->input)];
            }
        }

        return $items;
    }

    /** @return array<string, string> */
    private static function attachmentPart(LlmAttachment $attachment): array
    {
        if ($attachment->isPdf()) {
            return ['type' => 'input_file', 'filename' => $attachment->filename, 'file_data' => 'data:application/pdf;base64,'.base64_encode($attachment->contents)];
        }
        if ($attachment->isImage()) {
            return ['type' => 'input_image', 'image_url' => 'data:'.$attachment->mediaType.';base64,'.base64_encode($attachment->contents)];
        }

        return ['type' => 'input_text', 'text' => \sprintf("<document name=\"%s\">\n%s\n</document>", htmlspecialchars($attachment->filename, \ENT_QUOTES), $attachment->contents)];
    }

    /** The schema's name: letters, digits, "_" and "-", at most 64. */
    private static function schemaName(string $purpose): string
    {
        $name = substr((string) preg_replace('/[^A-Za-z0-9_-]+/', '_', $purpose), 0, 64);

        return '' === $name ? 'answer' : $name;
    }

    /** @param array<string, mixed> $data */
    private static function errorMessage(array $data): string
    {
        $message = $data['error']['message'] ?? 'no details';

        // OpenAI echoes a wrong key partly ("Incorrect API key provided: sk-...abcd"): never pass it on.
        return (string) preg_replace('/\bsk-[A-Za-z0-9_*.\-]+/', 'sk-…', \is_string($message) ? $message : 'no details');
    }
}
