<?php

namespace App\Chat\Application\Tool;

use App\Chat\Domain\Error\UnknownTool;
use App\Chat\Domain\WriteQueue;
use App\Shared\Application\Llm\LlmTool;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\DomainError;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The account tools of the chat (PRD §7.19), from every ChatToolbox: reads run at once; writes are checked and queued
 * (at most 50), then run in order when the user says yes, each checked again as its HTTP endpoint would. A refusal is
 * never fatal to the turn: it goes back to the model (or into the action list) with its code.
 */
final class ChatTools
{
    /** @var array<string, ChatTool>|null */
    private ?array $tools = null;

    /** @param iterable<ChatToolbox> $toolboxes */
    public function __construct(
        #[AutowireIterator('app.chat_toolbox')]
        private readonly iterable $toolboxes,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return list<LlmTool> */
    public function definitions(): array
    {
        return array_values(array_map(static fn (ChatTool $t): LlmTool => new LlmTool($t->name, $t->description, $t->inputSchema), $this->all()));
    }

    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    public function get(string $name): ChatTool
    {
        return $this->all()[$name] ?? throw new UnknownTool($name);
    }

    /**
     * A tool call of the model: a read's result, or a write's place in the queue.
     *
     * @param array<string, mixed> $input
     *
     * @return array{result: array<string, mixed>, queue: WriteQueue}
     *
     * @throws DomainError the refusal, for the model
     */
    public function call(Caller $caller, string $name, array $input, WriteQueue $queue): array
    {
        $tool = $this->get($name);
        $toolInput = new ToolInput($input);
        if (!$tool->isWrite()) {
            return ['result' => $tool->run($caller, $toolInput), 'queue' => $queue];
        }
        $label = $tool->guard($caller, $toolInput);
        $queue = $queue->with($name, $input, $label);
        $item = $queue->items()[\count($queue->items()) - 1];

        return [
            'result' => ['queued' => true, 'id' => $item['id'], 'label' => $label, 'pending_changes' => \count($queue->items()), 'note' => 'Not done yet: it runs when the user says yes. Summarize the pending changes and ask the user to confirm.'],
            'queue' => $queue,
        ];
    }

    /**
     * Runs the queued writes in order (the user said yes). Each one is checked again; a refusal fails that one only.
     *
     * @return list<array<string, mixed>> the actions: {id, tool, label, status: done|failed, error?, result?, job_id?, language?}
     */
    public function execute(Caller $caller, WriteQueue $queue): array
    {
        $actions = [];
        foreach ($queue->items() as $item) {
            $action = ['id' => $item['id'], 'tool' => $item['tool'], 'label' => $item['label']];
            try {
                $tool = $this->get($item['tool']);
                if (!$tool->isWrite()) {
                    throw new UnknownTool($item['tool']);
                }
                $result = $tool->run($caller, new ToolInput($item['input']));
                $action['status'] = 'done';
                $action['result'] = $result;
                foreach (['job_id', 'language', 'url'] as $key) {
                    if (\is_string($result[$key] ?? null)) {
                        $action[$key] = $result[$key];
                    }
                }
            } catch (DomainError $e) {
                $action['status'] = 'failed';
                $action['error'] = self::error($e);
            } catch (\Throwable $e) {
                $this->logger->error('Chat write {tool} failed: {message}', ['tool' => $item['tool'], 'message' => $e->getMessage(), 'exception' => $e]);
                $action['status'] = 'failed';
                $action['error'] = ['code' => 'INTERNAL_ERROR', 'message' => 'The change could not be made.'];
            }
            $actions[] = $action;
        }

        return $actions;
    }

    /** @return array{code: string, message: string, details?: array<string, mixed>} */
    public static function error(DomainError $e): array
    {
        $error = ['code' => $e->errorCode(), 'message' => $e->getMessage()];
        if ([] !== $e->details()) {
            $error['details'] = $e->details();
        }

        return $error;
    }

    /** @return array<string, ChatTool> */
    private function all(): array
    {
        if (null === $this->tools) {
            $this->tools = [];
            foreach ($this->toolboxes as $toolbox) {
                foreach ($toolbox->tools() as $tool) {
                    $this->tools[$tool->name] = $tool;
                }
            }
        }

        return $this->tools;
    }
}
