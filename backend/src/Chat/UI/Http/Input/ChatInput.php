<?php

namespace App\Chat\UI\Http\Input;

use App\Chat\UI\Http\Output\ChatDraftOutput;
use App\Chat\UI\Http\Output\ChatPendingWriteOutput;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * POST /chat (PRD §8.10): messages[] 1–40 {role: user|assistant, content 1–20000}, the last one the user's; mode
 * create (default) | draft; the current draft; the record the user clicked (item); and the changes waiting for the
 * user's yes (pending_writes, at most 50: the client keeps them, like the draft).
 */
final class ChatInput
{
    /** @var list<array{role: string, content: string}>|null */
    #[OA\Property(type: 'array', minItems: 1, maxItems: 40, items: new OA\Items(
        required: ['role', 'content'],
        properties: [
            new OA\Property(property: 'role', type: 'string', enum: ['user', 'assistant']),
            new OA\Property(property: 'content', type: 'string', maxLength: 20000, minLength: 1),
        ],
        type: 'object',
    ))]
    #[Assert\NotNull]
    #[Assert\Count(min: 1, max: 40)]
    #[Assert\All([new Assert\Collection(
        fields: [
            'role' => [new Assert\NotNull(), new Assert\Choice(choices: ['user', 'assistant'])],
            'content' => [new Assert\NotNull(), new Assert\Type('string'), new Assert\Length(min: 1, max: 20000)],
        ],
        allowExtraFields: false,
    )])]
    public ?array $messages = null;

    #[OA\Property(enum: ['create', 'draft'], default: 'create')]
    #[Assert\Choice(choices: ['create', 'draft'])]
    public ?string $mode = 'create';

    /** @var array<string, mixed>|null */
    #[OA\Property(ref: new Model(type: ChatDraftOutput::class), nullable: true)]
    public ?array $draft = null;

    /** @var array{kind: string, id: string}|null */
    #[OA\Property(
        required: ['kind', 'id'],
        properties: [
            new OA\Property(property: 'kind', type: 'string', enum: ['questionnaire', 'organization', 'assignation', 'project']),
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        ],
        type: 'object',
        nullable: true,
    )]
    #[Assert\Collection(
        fields: [
            'kind' => [new Assert\NotNull(), new Assert\Choice(choices: ['questionnaire', 'organization', 'assignation', 'project'])],
            'id' => [new Assert\NotNull(), new Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])],
        ],
        allowExtraFields: false,
    )]
    public ?array $item = null;

    /** @var list<array<string, mixed>>|null */
    #[OA\Property(type: 'array', maxItems: 50, items: new OA\Items(ref: new Model(type: ChatPendingWriteOutput::class)), nullable: true)]
    #[Assert\Count(max: 50)]
    #[Assert\All([new Assert\Type('array')])]
    public ?array $pending_writes = null;

    #[Assert\Callback]
    public function validateLastMessage(ExecutionContextInterface $context): void
    {
        $messages = \is_array($this->messages) ? array_values($this->messages) : [];
        $last = [] === $messages ? null : $messages[\count($messages) - 1];
        if (\is_array($last) && 'user' !== ($last['role'] ?? null)) {
            $context->buildViolation('The last message must be the user\'s.')->atPath('messages')->addViolation();
        }
    }

    /** @return list<array{role: string, content: string}> */
    public function messages(): array
    {
        return array_values(array_map(static fn (array $m): array => ['role' => (string) $m['role'], 'content' => (string) $m['content']], (array) $this->messages));
    }
}
