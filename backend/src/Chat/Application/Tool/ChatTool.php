<?php

namespace App\Chat\Application\Tool;

use App\Shared\Application\Security\Caller;

/**
 * One account tool the assistant can call (PRD §7.19 "account tools available to the chat").
 *
 * A read runs at once. A write is first checked ($guard: the same permission and ownership checks as its
 * HTTP endpoint, plus the shape of its input), queued with the label the user sees, and only run when the user says
 * yes — when $guard runs again, then $run.
 */
final class ChatTool
{
    public const READ = 'read';
    public const WRITE = 'write';

    /**
     * @param array<string, mixed>                              $inputSchema JSON schema of the input
     * @param \Closure(Caller, ToolInput): array<string, mixed> $run         what it does; returns the result
     * @param (\Closure(Caller, ToolInput): string)|null        $guard       writes: checks without changing anything,
     *                                                                       returns the label shown to the user
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly string $kind,
        private readonly \Closure $run,
        private readonly ?\Closure $guard = null,
    ) {
    }

    /**
     * @param array<string, mixed>                              $properties
     * @param list<string>                                      $required
     * @param \Closure(Caller, ToolInput): array<string, mixed> $run
     */
    public static function read(string $name, string $description, array $properties, array $required, \Closure $run): self
    {
        return new self($name, $description, self::schema($properties, $required), self::READ, $run);
    }

    /**
     * @param array<string, mixed>                              $properties
     * @param list<string>                                      $required
     * @param \Closure(Caller, ToolInput): string               $guard
     * @param \Closure(Caller, ToolInput): array<string, mixed> $run
     */
    public static function write(string $name, string $description, array $properties, array $required, \Closure $guard, \Closure $run): self
    {
        return new self($name, $description.' It is a change: it is queued and runs only when the user says yes.', self::schema($properties, $required), self::WRITE, $run, $guard);
    }

    public function isWrite(): bool
    {
        return self::WRITE === $this->kind;
    }

    /** @return array<string, mixed> */
    public function run(Caller $caller, ToolInput $input): array
    {
        if (null !== $this->guard) {
            ($this->guard)($caller, $input);
        }

        return ($this->run)($caller, $input);
    }

    /** Writes: the checks only; returns the label of the queued change. */
    public function guard(Caller $caller, ToolInput $input): string
    {
        return null === $this->guard ? $this->name : ($this->guard)($caller, $input);
    }

    /**
     * @param array<string, mixed> $properties
     * @param list<string>         $required
     *
     * @return array<string, mixed>
     */
    private static function schema(array $properties, array $required): array
    {
        return ['type' => 'object', 'properties' => [] === $properties ? new \stdClass() : $properties, 'required' => $required];
    }
}
