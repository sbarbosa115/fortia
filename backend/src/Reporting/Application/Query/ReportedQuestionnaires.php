<?php

namespace App\Reporting\Application\Query;

use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Questionnaires\Application\Query\QuestionnaireView;
use App\Reporting\Domain\Model\QuestionProfile;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotFound;

/** The questionnaire a report is about, loaded for its owner (or an Admin): another tenant's id is a 404. */
final class ReportedQuestionnaires
{
    public function __construct(private readonly QuestionnaireQueries $questionnaires)
    {
    }

    public function get(Caller $caller, string $questionnaireId): QuestionnaireView
    {
        $questionnaire = $this->questionnaires->find($questionnaireId);
        if (null === $questionnaire || !$caller->owns($questionnaire->customerId())) {
            throw new NotFound('QUESTIONNAIRE_NOT_FOUND', 'The questionnaire does not exist.');
        }

        return $questionnaire;
    }

    /**
     * Its answerable questions, in order, as the charts see them.
     *
     * @return list<QuestionProfile>
     */
    public static function profiles(QuestionnaireView $questionnaire): array
    {
        return array_values(array_filter(array_map(QuestionProfile::fromQuestion(...), $questionnaire->questions())));
    }

    public static function isDiagnostic(QuestionnaireView $questionnaire): bool
    {
        return 'diagnostic' === $questionnaire->type() || 'diagnostic' === ($questionnaire->onCompleted()['type'] ?? null);
    }
}
