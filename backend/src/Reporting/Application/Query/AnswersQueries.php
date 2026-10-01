<?php

namespace App\Reporting\Application\Query;

use App\Organizations\Application\Query\OrganizationQueries;
use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Questionnaires\Application\Query\QuestionnaireView;
use App\Reporting\Application\Port\SessionReadModel;
use App\Reporting\Domain\Model\AnswersPage;
use App\Responses\Application\Query\SessionQueries;
use App\Shared\Domain\Error\NotFound;

/**
 * The answers of a questionnaire (PRD §8.4 GET /questionnaire/{id}/answers, §10.8): a page of its sessions, each
 * enriched with the organization member who answered it and, with include_chain, the chain stage the respondent
 * reached; plus the questionnaire's header data and its generated chain stages.
 */
final class AnswersQueries
{
    public function __construct(
        private readonly SessionReadModel $sessions,
        private readonly SessionQueries $sessionQueries,
        private readonly QuestionnaireQueries $questionnaires,
        private readonly OrganizationQueries $organizations,
    ) {
    }

    /**
     * @return array{items: list<array{session: array<string, mixed>, member: array{organization_user_id: string, name: string, email: string|null, phone: string|null}|null, chain: array{stage: int, total_stages: int}|null}>, next_cursor: string|null, total: int, questionnaire: array{questionnaire_id: string, title: string, type: string, is_chain: bool, public_id: string}, generated_stages: list<array<string, mixed>>}
     */
    public function page(QuestionnaireView $questionnaire, AnswersPage $page, bool $includeChain): array
    {
        $rows = $this->sessions->page($questionnaire->id(), $page->statuses, $page->assignationsId, $page->offset, $page->limit);
        $total = $this->sessions->count($questionnaire->id(), $page->statuses, $page->assignationsId);
        $chained = $includeChain && ($this->isChain($questionnaire) || [] !== $this->questionnaires->stagesOf($questionnaire->id()));
        $members = [];
        $items = [];
        foreach ($rows as $session) {
            $items[] = [
                'session' => $session,
                'member' => $this->memberOf($session, $members),
                'chain' => $includeChain ? ($chained ? $this->chainOf((string) $session['session_id']) : ['stage' => 1, 'total_stages' => 1]) : null,
            ];
        }

        return [
            'items' => $items,
            'next_cursor' => $page->nextCursor($total),
            'total' => $total,
            'questionnaire' => $this->header($questionnaire),
            'generated_stages' => array_map(static fn (QuestionnaireView $stage): array => [
                'questionnaire_id' => $stage->id(),
                'title' => $stage->title(),
                'origin_session_id' => $stage->data['origin_session_id'] ?? null,
                'created_at' => $stage->data['created_at'] ?? null,
            ], $this->questionnaires->stagesOf($questionnaire->id())),
        ];
    }

    /**
     * One session of the questionnaire (or of one of its generated stages) with its results.
     *
     * @return array{session: array<string, mixed>, member: array{organization_user_id: string, name: string, email: string|null, phone: string|null}|null, results: array<string, mixed>|null, flow: array<string, mixed>|null}
     */
    public function detail(QuestionnaireView $questionnaire, string $sessionId): array
    {
        $session = $this->sessionQueries->find($sessionId) ?? throw self::sessionNotFound();
        if ($session->questionnaireId() !== $questionnaire->id()) {
            $stage = $this->questionnaires->find($session->questionnaireId());
            if (null === $stage || $stage->parent() !== $questionnaire->id()) {
                throw self::sessionNotFound();
            }
        }
        $members = [];

        return [
            'session' => $session->data,
            'member' => $this->memberOf($session->data, $members),
            'results' => $this->sessionQueries->resultsOf($sessionId),
            'flow' => $this->questionnaires->flowOf($questionnaire->id())?->data,
        ];
    }

    /** @return array{questionnaire_id: string, title: string, type: string, is_chain: bool, public_id: string} */
    private function header(QuestionnaireView $questionnaire): array
    {
        $flow = $this->questionnaires->flowOf($questionnaire->id());

        return [
            'questionnaire_id' => $questionnaire->id(),
            'title' => $questionnaire->title(),
            'type' => $questionnaire->type(),
            'is_chain' => $this->isChain($questionnaire),
            'public_id' => null !== $flow ? $flow->slug() : ((string) ($questionnaire->data['slug'] ?? '') ?: $questionnaire->id()),
        ];
    }

    private function isChain(QuestionnaireView $questionnaire): bool
    {
        return (bool) ($questionnaire->data['is_chain'] ?? false) || [] !== $this->questionnaires->promptsOf($questionnaire->id());
    }

    /**
     * @param array<string, mixed>                                                                                          $session
     * @param array<string, array{organization_user_id: string, name: string, email: string|null, phone: string|null}|null> $members a per-request cache
     *
     * @return array{organization_user_id: string, name: string, email: string|null, phone: string|null}|null
     */
    private function memberOf(array $session, array &$members): ?array
    {
        $id = $session['organization_user_id'] ?? null;
        if (!\is_string($id) || '' === $id) {
            return null;
        }
        if (!\array_key_exists($id, $members)) {
            $member = $this->organizations->member($id);
            $members[$id] = null === $member ? null : [
                'organization_user_id' => $id,
                'name' => (string) ($member['name'] ?? ''),
                'email' => isset($member['email']) ? (string) $member['email'] : null,
                'phone' => isset($member['phone']) ? (string) $member['phone'] : null,
            ];
        }

        return $members[$id];
    }

    /**
     * How far the respondent of a first-stage session went (PRD §7.8), as the Responses context walks the chain, and
     * how many stages the flow runs.
     *
     * @return array{stage: int, total_stages: int}
     */
    private function chainOf(string $sessionId): array
    {
        $chain = $this->sessionQueries->chainOf($sessionId);
        $stage = null === $chain ? 1 : max(1, \count($chain['stages']));

        return ['stage' => $stage, 'total_stages' => max($stage, $chain['total_stages'] ?? 1)];
    }

    private static function sessionNotFound(): NotFound
    {
        return new NotFound('SESSION_NOT_FOUND', 'The session does not exist.');
    }
}
