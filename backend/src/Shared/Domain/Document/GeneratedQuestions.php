<?php

namespace App\Shared\Domain\Document;

use App\Shared\Domain\Error\UpstreamFailed;
use App\Shared\Domain\Ids;

/**
 * Cleaning up LLM output, always when generating questions (PRD §7.6): quiz funnel, chain stages, LinkedIn and chat
 * all pass their questions through here before storing them.
 */
final class GeneratedQuestions
{
    /**
     * @param array<int|string, mixed> $questions
     *
     * @return list<array<string, mixed>>
     *
     * @throws UpstreamFailed when no question is left
     */
    public static function clean(array $questions): array
    {
        $clean = [];
        foreach ($questions as $question) {
            if (!\is_array($question)) {
                continue;
            }
            // 1. Keep only the first control. 2. Remove questions without one.
            $control = Questions::control($question);
            if (null === $control) {
                continue;
            }
            $question['options'] = [$control];
            $clean[] = $question;
        }

        // 3. Clear the runtime fields. 4. Renumber order from 0.
        $clean = Questions::normalizeAll(array_map(static function (array $q): array {
            unset($q['order']);

            return $q;
        }, $clean));

        // 5. Deduplicate option values.
        $clean = OptionValues::dedupeQuestions($clean);

        // 6. A new UUID for each question and each control name.
        foreach ($clean as $i => $question) {
            $clean[$i]['id'] = Ids::uuid4();
            $clean[$i]['order'] = $i;
            foreach ($question['options'] as $j => $_) {
                $clean[$i]['options'][$j]['name'] = Ids::uuid4();
            }
        }

        // 7. If no question remains, fail.
        if ([] === $clean) {
            throw new UpstreamFailed('GENERATION_FAILED', 'The language model returned no usable question.');
        }

        return $clean;
    }
}
