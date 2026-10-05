<?php

namespace App\Assignations\Application\Query;

use App\Assignations\Domain\Audience;
use App\Assignations\Domain\FollowUpProgress;
use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\Model\AssignationAnswer;
use App\Assignations\Domain\Repository\AssignationAnswerRepository;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Organizations\Application\Query\OrganizationQueries;
use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Responses\Application\Query\SessionQueries;
use App\Responses\Application\Query\SessionView;
use App\Shared\Domain\Document\ControlType;
use App\Shared\Domain\Document\Questions;
use App\Shared\Domain\Document\TableAnswer;

/**
 * The enriched assignations of the console and the respondent page (PRD §8.8): the stored fields plus
 * organization_name, questionnaire_name, questionnaire_url ({FRONTEND_URL}/a/{id}), audience_size, the current
 * attempt, progress (§7.11), completed and review_status, and the attempts with their session's state.
 *
 * Progress — default: {completed: audience members with a response, total: audience size, unit: "respondents"};
 * follow-up: {completed: answerable questions answered or skipped, total, unit: "questions", current_question}.
 *
 * The detail for the owner also carries each follow-up attempt's answers with their review (the console's review
 * table); anonymous callers (the respondent page) never see them, nor the internal description.
 */
final class AssignationDetails
{
    public function __construct(
        private readonly AssignationRepository $assignations,
        private readonly AssignationAnswerRepository $answers,
        private readonly FollowUpStatus $followUps,
        private readonly OrganizationQueries $organizations,
        private readonly QuestionnaireQueries $questionnaires,
        private readonly SessionQueries $sessions,
        private readonly string $frontendUrl,
    ) {
    }

    /**
     * One page of GET /assignations, newest first.
     *
     * @param string|null $customerId null = every account (Admin)
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function page(?string $customerId, ?string $type, ?string $questionnaireId, int $page, int $pageSize): array
    {
        $rows = $this->assignations->page($customerId, $type, ($page - 1) * $pageSize, $pageSize, $questionnaireId);
        $cache = [];

        return [
            'items' => array_map(fn (Assignation $a): array => $this->enrich($a, false, $cache), $rows),
            'total' => $this->assignations->count($customerId, $type, $questionnaireId),
        ];
    }

    /**
     * GET /assignations/{id}. Null when it does not exist; the caller checks ownership.
     *
     * @param bool $owner the console owner (or Admin): with the description and the follow-up answers
     *
     * @return array<string, mixed>|null
     */
    public function find(string $assignationsId, bool $owner): ?array
    {
        $assignation = $this->assignations->find($assignationsId);
        if (null === $assignation) {
            return null;
        }
        $cache = [];
        $data = $this->enrich($assignation, $owner, $cache);
        if (!$owner) {
            $data['description'] = null;
        }

        return $data;
    }

    /**
     * The respondents of an assignation by its id, for callers outside this context (the chat assistant's
     * list_assignation_respondents, PRD §7.19). Null when it does not exist; the caller checks ownership with
     * customer_id.
     *
     * @return array{customer_id: string, respondents: list<array<string, mixed>>, next_offset: int|null}|null
     */
    public function respondentsOf(string $assignationsId, int $offset, int $limit): ?array
    {
        $assignation = $this->assignations->find($assignationsId);
        if (null === $assignation) {
            return null;
        }

        return ['customer_id' => $assignation->customerId()] + $this->respondents($assignation, $offset, $limit);
    }

    /**
     * GET /assignations/{id}/respondents: the audience by name, from $offset, with each member's status
     * (pending | in_progress | completed), session, stages and attempts.
     *
     * @return array{respondents: list<array<string, mixed>>, next_offset: int|null}
     */
    public function respondents(Assignation $assignation, int $offset, int $limit): array
    {
        $audience = Audience::select($assignation->audience(), $this->organizations->membersOf($assignation->organizationId()));
        usort($audience, static fn (array $a, array $b): int => [(string) $a['name'], (string) $a['organization_user_id']] <=> [(string) $b['name'], (string) $b['organization_user_id']]);
        $slice = \array_slice($audience, $offset, $limit);

        $byMember = [];
        foreach ($this->sessions->ofAssignation($assignation->assignationsId()) as $session) {
            if ($session->questionnaireId() === $assignation->questionnaireId()) {
                $byMember[(string) ($session->data['organization_user_id'] ?? '')][] = $session;
            }
        }
        $shared = $assignation->isFollowUp() ? array_values(array_filter(array_map(
            fn (array $attempt): ?SessionView => $this->sessions->find($attempt['session_id']),
            $assignation->attempts(),
        ))) : null;

        $rows = [];
        foreach ($slice as $member) {
            $sessions = $shared ?? ($byMember[(string) $member['organization_user_id']] ?? []);
            usort($sessions, static fn (SessionView $a, SessionView $b): int => [(string) $a->data['started_at'], $a->attempt()] <=> [(string) $b->data['started_at'], $b->attempt()]);
            $latest = [] === $sessions ? null : $sessions[\count($sessions) - 1];
            $chain = null === $latest ? null : $this->sessions->chainOf($latest->id());
            $completedStages = null === $chain ? 0 : \count(array_filter($chain['stages'], static fn (SessionView $s): bool => $s->isEnded()));
            $totalStages = null === $chain ? 1 : $chain['total_stages'];
            $rows[] = [
                'organization_user_id' => $member['organization_user_id'],
                'organization_user_name' => $member['name'],
                'organization_user_email' => $member['email'],
                'status' => match (true) {
                    null === $latest => 'pending',
                    $completedStages >= $totalStages => 'completed',
                    default => 'in_progress',
                },
                'session_id' => $latest?->id(),
                'completed_stages' => $completedStages,
                'total_stages' => $totalStages,
                'attempts' => \count($sessions),
                'attempts_detail' => array_map(static fn (SessionView $s, int $i): array => [
                    'number' => $i + 1,
                    'session_id' => $s->id(),
                    'status' => $s->status(),
                    'started_at' => $s->data['started_at'] ?? null,
                    'ended_at' => $s->data['ended_at'] ?? null,
                ], $sessions, array_keys($sessions)),
            ];
        }

        $next = $offset + $limit;

        return ['respondents' => $rows, 'next_offset' => $next < \count($audience) ? $next : null];
    }

    /**
     * @param array<string, mixed> $cache organization members and names, questionnaire titles already read
     *
     * @return array<string, mixed>
     */
    private function enrich(Assignation $assignation, bool $withAnswers, array &$cache): array
    {
        $organization = $this->organization($assignation->organizationId(), $cache);
        $audience = Audience::select($assignation->audience(), $organization['members']);
        $data = AssignationQueries::assignationData($assignation);
        $data['organization_name'] = $organization['name'];
        $data['questionnaire_name'] = $this->questionnaireName($assignation->questionnaireId(), $cache);
        $data['questionnaire_url'] = rtrim($this->frontendUrl, '/').'/a/'.$assignation->assignationsId();
        $data['audience_size'] = \count($audience);
        $data['attempt'] = $assignation->currentAttempt();

        if ($assignation->isFollowUp()) {
            $progress = $this->followUps->of($assignation);
            $data['progress'] = ['completed' => $progress->completed, 'total' => $progress->total, 'unit' => 'questions', 'current_question' => $progress->currentQuestion];
            $data['completed'] = $progress->ended;
            $data['review_status'] = $progress->reviewStatus;
            $data['attempts'] = $this->attempts($assignation, $withAnswers);
        } else {
            $answered = array_map(static fn (AssignationAnswer $a): string => $a->organizationUserId(), $this->answers->listByAssignation($assignation->assignationsId()));
            $completed = \count(array_filter($audience, static fn (array $m): bool => \in_array($m['organization_user_id'], $answered, true)));
            $data['progress'] = ['completed' => $completed, 'total' => \count($audience), 'unit' => 'respondents', 'current_question' => null];
            $data['completed'] = \count($audience) > 0 && $completed >= \count($audience);
            $data['review_status'] = null;
            $data['attempts'] = [];
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function attempts(Assignation $assignation, bool $withAnswers): array
    {
        $out = [];
        foreach ($assignation->attempts() as $attempt) {
            $session = $this->sessions->find($attempt['session_id']);
            $progress = null === $session ? null : FollowUpProgress::ofSession($session->questions(), $session->isEnded(), $session->attempt(), $assignation->requiresReview());
            $out[] = [
                'number' => $attempt['number'],
                'session_id' => $attempt['session_id'],
                'created_at' => $attempt['created_at'],
                'status' => $session?->status(),
                'started_at' => $session?->data['started_at'] ?? null,
                'ended_at' => $session?->data['ended_at'] ?? null,
                'completed' => $progress->ended ?? false,
                'review_status' => $progress->reviewStatus ?? FollowUpProgress::NOT_READY,
                'answers' => $withAnswers && null !== $session ? $this->answerRows($session) : null,
            ];
        }

        return $out;
    }

    /**
     * The answerable questions of an attempt's session with their answer and review (the follow-up's table, PRD
     * §10.11): review_state is `locked` (approved in an earlier attempt), `approved` / `rejected` (in this attempt) or
     * `not_reviewed`.
     *
     * @return list<array<string, mixed>>
     */
    private function answerRows(SessionView $session): array
    {
        $values = $this->sessions->answersOf($session->id()) ?? [];
        $rows = [];
        $position = 0;
        foreach ($session->questions() as $question) {
            if (!Questions::isAnswerable($question)) {
                continue;
            }
            $value = $values[$position] ?? null;
            ++$position;
            $control = Questions::control($question) ?? [];
            $locked = false;
            $skipped = false;
            $answeredAt = null;
            foreach ((array) ($question['options'] ?? []) as $c) {
                if (\is_array($c)) {
                    $locked = $locked || true === ($c['locked'] ?? false);
                    $skipped = $skipped || true === ($c['skipped'] ?? false);
                    $answeredAt ??= \is_string($c['timestamp'] ?? null) && '' !== $c['timestamp'] ? $c['timestamp'] : null;
                }
            }
            $review = \is_array($question['review'] ?? null) ? $question['review'] : null;
            $current = null !== $review && (int) ($review['attempt'] ?? 0) === $session->attempt() ? (string) ($review['status'] ?? '') : null;
            $rows[] = [
                'question_id' => (string) ($question['id'] ?? ''),
                'position' => $position,
                'title' => (string) ($question['title'] ?? ''),
                'type' => (string) ($control['type'] ?? ''),
                'answer' => self::answerText($value, $control),
                'answer_table' => ControlType::Table->value === ($control['type'] ?? null) ? TableAnswer::structured($control['value'] ?? null, $control) : null,
                'skipped' => $skipped && !Questions::isAnswered($question),
                'answered_at' => Questions::isResolved($question) ? $answeredAt : null,
                'locked' => $locked,
                'review' => null === $review ? null : [
                    'status' => (string) ($review['status'] ?? ''),
                    'comment' => isset($review['comment']) && '' !== $review['comment'] ? (string) $review['comment'] : null,
                    'reviewed_at' => $review['reviewed_at'] ?? null,
                    'attempt' => (int) ($review['attempt'] ?? 0),
                ],
                'review_state' => match (true) {
                    $locked => 'locked',
                    'approved' === $current => 'approved',
                    'rejected' === $current => 'rejected',
                    default => 'not_reviewed',
                },
            ];
        }

        return $rows;
    }

    /**
     * @param array{title: string, value: mixed, min?: int|float, max?: int|float}|null $answer
     * @param array<string, mixed>                                                      $control
     */
    private static function answerText(?array $answer, array $control): ?string
    {
        if (ControlType::Table->value === ($control['type'] ?? null)) {
            // One line per row, "Column: value; …": the rows are objects, not values to join.
            $text = TableAnswer::toText($control['value'] ?? null, $control);

            return '' === $text ? null : $text;
        }
        $value = $answer['value'] ?? null;
        if (null === $value || [] === $value) {
            return null;
        }
        if (true === ControlType::tryFrom((string) ($control['type'] ?? ''))?->isSelection()) {
            // The options' labels, not their stored values (a slug like "se-usara-nombre").
            $labels = [];
            foreach ((array) ($control['options'] ?? []) as $option) {
                if (\is_array($option) && \is_scalar($option['value'] ?? $option['label'] ?? null)) {
                    $labels[(string) ($option['value'] ?? $option['label'])] = (string) ($option['label'] ?? '');
                }
            }
            $value = array_map(static fn ($v) => \is_scalar($v) ? ($labels[(string) $v] ?? $v) : $v, (array) $value);

            return implode(', ', array_map(static fn ($v): string => \is_scalar($v) ? (string) $v : '', $value));
        }
        if (\is_array($value)) {
            return implode(', ', array_map(static fn ($v): string => \is_scalar($v) ? basename((string) $v) : '', $value));
        }
        if (isset($answer['max']) && (\is_int($value) || \is_float($value))) {
            return $value.' / '.$answer['max'];
        }

        return \is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param array<string, mixed> $cache
     *
     * @return array{name: string, members: list<array<string, mixed>>}
     */
    private function organization(string $organizationId, array &$cache): array
    {
        if (!isset($cache['org'][$organizationId])) {
            $organization = $this->organizations->find($organizationId);
            /** @var list<array<string, mixed>> $members */
            $members = $organization['organization_users'] ?? [];
            $cache['org'][$organizationId] = ['name' => (string) ($organization['name'] ?? ''), 'members' => $members];
        }

        return $cache['org'][$organizationId];
    }

    /** @param array<string, mixed> $cache */
    private function questionnaireName(string $questionnaireId, array &$cache): string
    {
        if (!isset($cache['q'][$questionnaireId])) {
            $cache['q'][$questionnaireId] = $this->questionnaires->find($questionnaireId)?->title() ?? '';
        }

        return $cache['q'][$questionnaireId];
    }
}
