<?php

namespace App\Responses\Infrastructure\Llm;

use App\Responses\Application\Job\AnswerEvaluationJob;
use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmResponse;
use App\Shared\Infrastructure\Llm\Fake\FakeLlmResponder;

/**
 * Offline grading of an answer (PRD §7.9): an answer of five words or more meets every criterion (40/50); two to
 * four words are related but too thin (10/50); a single word is unrelated. Predictable enough to try both outcomes
 * in the respondent app.
 */
final class AnswerEvaluationResponder implements FakeLlmResponder
{
    public function supports(LlmRequest $request): bool
    {
        return AnswerEvaluationJob::PURPOSE === $request->purpose;
    }

    public function respond(LlmRequest $request): LlmResponse
    {
        $answer = (string) ($request->context['answer'] ?? '');
        $words = \count(preg_split('/\s+/u', trim($answer), -1, \PREG_SPLIT_NO_EMPTY) ?: []);
        $grade = $words >= 5 ? 40 : 10;
        $criteria = array_values(array_map('strval', (array) ($request->context['criteria'] ?? [])));

        return LlmResponse::json([
            'related' => $words >= 2,
            'grades' => array_map(static fn (string $c): array => ['criterion' => $c, 'grade' => $grade], $criteria ?: ['answers the question']),
            'improvement_message' => $words >= 5 ? '' : 'Could you tell us a bit more? Add a concrete example or some detail.',
        ]);
    }
}
