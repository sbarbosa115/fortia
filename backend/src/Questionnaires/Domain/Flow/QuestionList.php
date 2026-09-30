<?php

namespace App\Questionnaires\Domain\Flow;

use App\Shared\Domain\Document\OptionValues;
use App\Shared\Domain\Document\Questions;
use App\Shared\Domain\Ids;

/**
 * The questions of a stored questionnaire: normalized (runtime fields cleared, PRD §6.5 shape), in order and
 * renumbered from 0, with a unique id for every question and a name for every control, and duplicate option values
 * fixed (§7.5).
 */
final class QuestionList
{
    /**
     * @param list<array<string, mixed>> $questions
     *
     * @return list<array<string, mixed>>
     */
    public static function prepare(array $questions): array
    {
        $questions = OptionValues::dedupeQuestions(Questions::normalizeAll($questions));
        usort($questions, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        $seen = [];
        foreach ($questions as $i => $question) {
            if ('' === $question['id'] || isset($seen[$question['id']])) {
                $questions[$i]['id'] = Ids::uuid4();
            }
            $seen[$questions[$i]['id']] = true;
            $questions[$i]['order'] = $i;
            foreach ($question['options'] as $j => $control) {
                if ('' === $control['name']) {
                    $questions[$i]['options'][$j]['name'] = Ids::uuid4();
                }
            }
        }

        return $questions;
    }
}
