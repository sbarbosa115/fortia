<?php

namespace App\Generation\UI\Http\Input;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** PRD §8.4 POST /questionnaire/prompt: the chain, the answers to the stage before, and that stage's session. */
final class PromptStageInput
{
    public const MAX_ANSWERS = 200;
    public const MAX_QUESTION_CHARS = 1_000;
    public const MAX_ANSWER_CHARS = 10_000;

    /** The parent: the chain's root questionnaire (or one of its stages). */
    #[Assert\NotNull]
    #[Assert\Uuid]
    public ?string $questionnaire_id = null;

    /** @var array<int|string, mixed>|null each {question: string, answer: string} */
    #[Assert\NotNull]
    #[OA\Property(type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'question', type: 'string'),
        new OA\Property(property: 'answer', type: 'string'),
    ], type: 'object'))]
    public ?array $answers = null;

    #[Assert\Uuid]
    public ?string $session_id = null;

    /** Each answer is {question: string, answer: string}. */
    #[Assert\Callback]
    public function validateAnswers(ExecutionContextInterface $context): void
    {
        if (!\is_array($this->answers)) {
            return;
        }
        if (!array_is_list($this->answers) || \count($this->answers) > self::MAX_ANSWERS) {
            $context->buildViolation(\sprintf('The answers must be a list of at most %d items.', self::MAX_ANSWERS))->atPath('answers')->addViolation();

            return;
        }
        foreach ($this->answers as $i => $answer) {
            $question = \is_array($answer) ? ($answer['question'] ?? null) : null;
            $value = \is_array($answer) ? ($answer['answer'] ?? null) : null;
            if (!\is_string($question) || !\is_string($value)) {
                $context->buildViolation('Each answer must be {question, answer} with text values.')->atPath("answers[$i]")->addViolation();
            } elseif (mb_strlen($question) > self::MAX_QUESTION_CHARS || mb_strlen($value) > self::MAX_ANSWER_CHARS) {
                $context->buildViolation('This answer is too long.')->atPath("answers[$i]")->addViolation();
            }
        }
    }

    /** @return list<array{question: string, answer: string}> */
    public function answers(): array
    {
        $out = [];
        foreach ($this->answers ?? [] as $answer) {
            if (\is_array($answer) && \is_string($answer['question'] ?? null) && \is_string($answer['answer'] ?? null)) {
                $out[] = ['question' => $answer['question'], 'answer' => $answer['answer']];
            }
        }

        return $out;
    }
}
