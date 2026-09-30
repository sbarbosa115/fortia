<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Llm;

use Anthropic\Client;
use Anthropic\Messages\ToolUseBlock;
use App\Shared\Application\Llm\LanguageModel;
use App\Shared\Application\Llm\LlmAttachment;
use App\Shared\Application\Llm\LlmMessage;
use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmResponse;
use App\Shared\Application\Llm\LlmToolCall;
use App\Shared\Application\Llm\LlmUnavailable;

/**
 * The Claude API adapter, through the official Anthropic PHP SDK. Structured output uses output_config.format
 * (json_schema); tools are client tools run by the caller's loop (the chat), never forced.
 */
final class AnthropicLanguageModel implements LanguageModel
{
    private ?Client $client = null;

    public function __construct(
        private readonly string $apiKey,
        private readonly ModelCatalog $models,
    ) {
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        $model = $request->model ?? $this->models->forTier($request->tier);
        $outputConfig = [];
        if (null !== $request->jsonSchema) {
            $outputConfig['format'] = ['type' => 'json_schema', 'schema' => $request->jsonSchema];
        }
        if ($this->models->supportsEffort($model)) {
            // Generation is quality-sensitive; the fast tier is for cheap, simple calls.
            $outputConfig['effort'] = LlmRequest::TIER_FAST === $request->tier ? 'low' : 'high';
        }

        try {
            $message = $this->client()->messages->create(
                maxTokens: $request->maxTokens,
                messages: $this->messages($request),
                model: $model,
                outputConfig: [] === $outputConfig ? null : $outputConfig,
                system: '' === $request->system ? null : $request->system,
                tools: [] === $request->tools ? null : array_map(static fn ($tool): array => [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'inputSchema' => $tool->inputSchema,
                ], $request->tools),
            );
        } catch (\Throwable $e) {
            throw new LlmUnavailable('The language model could not be reached: '.$e->getMessage(), 0, $e);
        }

        $stopReason = (string) ($message->stopReason ?? '');
        if ('refusal' === $stopReason) {
            throw new LlmUnavailable('The language model declined the request.');
        }
        if ('max_tokens' === $stopReason) {
            throw new LlmUnavailable('The language model ran out of output tokens.');
        }

        $text = '';
        $calls = [];
        foreach ($message->content as $block) {
            if ($block instanceof ToolUseBlock) {
                $calls[] = new LlmToolCall($block->id, $block->name, (array) $block->input);
            } elseif ('text' === $block->type) {
                $text .= $block->text;
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
    private function messages(LlmRequest $request): array
    {
        $out = [];
        $attachmentsPending = [] !== $request->attachments;
        foreach ($request->messages as $message) {
            $content = [];
            if ('user' === $message->role && $attachmentsPending) {
                foreach ($request->attachments as $attachment) {
                    $content[] = $this->attachmentBlock($attachment);
                }
                $attachmentsPending = false;
            }
            foreach ($message->toolResults as $result) {
                $content[] = ['type' => 'tool_result', 'toolUseID' => $result->toolCallId, 'content' => $result->content, 'isError' => $result->isError];
            }
            if ('' !== $message->content) {
                $content[] = ['type' => 'text', 'text' => $message->content];
            }
            foreach ($message->toolCalls as $call) {
                $content[] = ['type' => 'tool_use', 'id' => $call->id, 'name' => $call->name, 'input' => (object) $call->input];
            }
            $out[] = ['role' => $message->role, 'content' => $this->contentOrText($message, $content)];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $content
     *
     * @return list<array<string, mixed>>|string
     */
    private function contentOrText(LlmMessage $message, array $content): array|string
    {
        return 1 === \count($content) && 'text' === $content[0]['type'] ? $message->content : $content;
    }

    /** @return array<string, mixed> */
    private function attachmentBlock(LlmAttachment $attachment): array
    {
        if ($attachment->isPdf()) {
            return ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => base64_encode($attachment->contents)], 'title' => $attachment->filename];
        }
        if ($attachment->isImage()) {
            return ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $attachment->mediaType, 'data' => base64_encode($attachment->contents)]];
        }

        return ['type' => 'document', 'source' => ['type' => 'text', 'mediaType' => 'text/plain', 'data' => $attachment->contents], 'title' => $attachment->filename];
    }

    private function client(): Client
    {
        if ('' === $this->apiKey) {
            throw new LlmUnavailable('ANTHROPIC_API_KEY is not set.');
        }

        return $this->client ??= new Client(apiKey: $this->apiKey);
    }
}
