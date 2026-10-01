<?php

namespace App\Questionnaires\Application\Command;

/**
 * Copies a questionnaire into its own account (PRD §7.5 "Copy", §8.4 POST /questionnaire/{id}/copy): title
 * "(copia) X" / "(copia - N) X", slug "<base>-copia[-N]" (in English "copy" for an English account, D23), with its
 * questions, flow, diagnostic and prompts (each prompt text copied to a key of its own). A copy has no answers, so it
 * can be edited. Emits QuestionnaireCreated (source "copy"). Returns the new questionnaire id. Assignations copy on conflict with it.
 */
final class CopyQuestionnaire
{
    public function __construct(public readonly string $questionnaireId)
    {
    }
}
