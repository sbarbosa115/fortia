<?php

namespace App\Chat\Application\Tool;

use App\Questionnaires\Application\Command\CopyQuestionnaire;
use App\Questionnaires\Application\Command\SetQuestionnaireActive;
use App\Questionnaires\Application\Query\ListingCriteria;
use App\Questionnaires\Application\Query\QuestionnaireDetails;
use App\Questionnaires\Application\Query\QuestionnaireListing;
use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Reporting\Application\Query\AnswersQueries;
use App\Reporting\Application\Query\DashboardQueries;
use App\Reporting\Application\Query\ReportedQuestionnaires;
use App\Reporting\Domain\Event\AnalyticsFetched;
use App\Responses\Application\Query\SessionQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Document\Questions;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Text;

/**
 * PRD §7.19 "Questionnaires": list_questionnaires, get_questionnaire, list_questionnaire_answers,
 * get_questionnaire_analytics, set_questionnaire_active and copy_questionnaire, with the checks of PRD §8.4: any
 * console user reads their account's (another account's is 404), the Active toggle and copies are AG.
 */
final class QuestionnaireTools implements ChatToolbox
{
    public function __construct(
        private readonly QuestionnaireListing $listing,
        private readonly QuestionnaireDetails $details,
        private readonly QuestionnaireQueries $questionnaires,
        private readonly ReportedQuestionnaires $reported,
        private readonly AnswersQueries $answers,
        private readonly DashboardQueries $dashboards,
        private readonly SessionQueries $sessions,
        private readonly CommandBus $commands,
        private readonly EventBus $events,
        private readonly string $frontendUrl,
    ) {
    }

    public function tools(): array
    {
        return [
            ChatTool::read(
                'list_questionnaires',
                'Lists the account\'s questionnaires, newest first. Every word of "search" must appear in the title.',
                ['search' => Schema::string('Words to look for in the title.', 200), 'is_active' => Schema::bool('Only active (true) or inactive (false) ones.')] + Schema::paging(),
                [],
                $this->list(...),
            ),
            ChatTool::read(
                'get_questionnaire',
                'The details of one questionnaire: its settings, questions, whether it has responses, and its public link.',
                ['questionnaire_id' => Schema::id('questionnaire')],
                ['questionnaire_id'],
                $this->get(...),
            ),
            ChatTool::read(
                'list_questionnaire_answers',
                'The completed responses of a questionnaire, newest first.',
                ['questionnaire_id' => Schema::id('questionnaire')] + Schema::paging(),
                ['questionnaire_id'],
                $this->answersOf(...),
            ),
            ChatTool::read(
                'get_questionnaire_analytics',
                'The analytics of a questionnaire: sessions started and completed, and the answers per question.',
                ['questionnaire_id' => Schema::id('questionnaire')],
                ['questionnaire_id'],
                $this->analytics(...),
            ),
            ChatTool::write(
                'set_questionnaire_active',
                'Activates or deactivates a questionnaire (an inactive one starts no new responses).',
                ['questionnaire_id' => Schema::id('questionnaire'), 'is_active' => Schema::bool('true to activate, false to deactivate.')],
                ['questionnaire_id', 'is_active'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::adminGroups($caller);
                    $input->bool('is_active');

                    return (string) $this->owned($caller, $input->uuid('questionnaire_id'))['title'];
                },
                function (Caller $caller, ToolInput $input): array {
                    $id = $input->uuid('questionnaire_id');
                    $this->commands->dispatch(new SetQuestionnaireActive($id, $input->bool('is_active')));

                    return ['questionnaire_id' => $id, 'is_active' => $input->bool('is_active')];
                },
            ),
            ChatTool::write(
                'copy_questionnaire',
                'Duplicates a questionnaire into the account, with its questions and ending; the copy has no responses, so it can be edited. Use it when the user wants to change a questionnaire that already has responses.',
                ['questionnaire_id' => Schema::id('questionnaire')],
                ['questionnaire_id'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::adminGroups($caller);
                    $questionnaire = $this->owned($caller, $input->uuid('questionnaire_id'));

                    return (string) $questionnaire['title'];
                },
                function (Caller $caller, ToolInput $input): array {
                    $copyId = (string) $this->commands->dispatch(new CopyQuestionnaire($input->uuid('questionnaire_id')));

                    return ['questionnaire_id' => $copyId, 'title' => (string) ($this->details->find($copyId)['title'] ?? '')];
                },
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function list(Caller $caller, ToolInput $input): array
    {
        $size = $input->pageSize();
        $offset = intdiv($input->offset(), $size) * $size;
        $active = $input->has('is_active') && null !== $input->all()['is_active'] ? $input->bool('is_active') : null;
        $criteria = new ListingCriteria(
            $caller->isAdmin() ? null : $caller->customerId,
            'ROOT',
            null,
            $active,
            Text::searchWords($input->optionalString('search') ?? ''),
            'created_at',
            'desc',
            intdiv($offset, $size) + 1,
            $size,
        );
        $page = $this->listing->page($criteria);

        return Schema::page(array_map(static fn (array $row): array => [
            'questionnaire_id' => $row['questionnaire_id'],
            'title' => $row['title'],
            'type' => $row['type'],
            'is_chain' => $row['is_chain'],
            'status' => $row['status'],
            'question_count' => $row['question_count'],
            'created_at' => $row['created_at'],
        ], $page['items']), $offset, (int) $page['total']);
    }

    /** @return array<string, mixed> */
    private function get(Caller $caller, ToolInput $input): array
    {
        $questionnaire = $this->owned($caller, $input->uuid('questionnaire_id'));
        $id = (string) $questionnaire['questionnaire_id'];
        $onCompleted = \is_array($questionnaire['on_completed'] ?? null) ? $questionnaire['on_completed'] : [];
        $flow = $this->questionnaires->flowOf($id);

        return [
            'questionnaire_id' => $id,
            'title' => $questionnaire['title'],
            'description' => $questionnaire['description'],
            'type' => $questionnaire['type'],
            'is_chain' => $questionnaire['is_chain'],
            'is_active' => $questionnaire['is_active'],
            'landing_page' => $questionnaire['landing_page'],
            'disclaimer' => $questionnaire['disclaimer'],
            'capture_user_data' => $questionnaire['capture_user_data'],
            'has_responses' => $this->sessions->hasAnyResponse($id),
            'public_url' => null === $flow ? null : rtrim($this->frontendUrl, '/').'/f/'.$flow->slug(),
            'created_at' => $questionnaire['created_at'],
            'questions' => array_map(static function (array $q): array {
                $control = Questions::control($q);

                return [
                    'title' => $q['title'] ?? '',
                    'type' => $control['type'] ?? null,
                    'choices' => array_map(static fn (array $o): string => (string) ($o['label'] ?? ''), array_values(array_filter((array) ($control['options'] ?? []), 'is_array'))),
                    'category' => $q['category'] ?? null,
                ];
            }, \array_slice(array_values(array_filter((array) $questionnaire['questions'], 'is_array')), 0, 100)),
            'tiers' => array_map(static fn (array $t): array => ['name' => $t['name'] ?? '', 'min' => $t['min'] ?? null, 'max' => $t['max'] ?? null], array_values(array_filter((array) ($onCompleted['tiers'] ?? []), 'is_array'))),
        ];
    }

    /** @return array<string, mixed> */
    private function answersOf(Caller $caller, ToolInput $input): array
    {
        $questionnaire = $this->reported->get($caller, $input->uuid('questionnaire_id'));
        $offset = $input->offset();
        $page = $this->answers->completedSlice($questionnaire, $offset, $input->pageSize());

        return Schema::page(array_map(static fn (array $item): array => [
            'session_id' => $item['session']['session_id'] ?? null,
            'respondent' => $item['member']['name'] ?? null,
            'email' => $item['member']['email'] ?? null,
            'status' => $item['session']['status'] ?? null,
            'started_at' => $item['session']['started_at'] ?? null,
            'ended_at' => $item['session']['ended_at'] ?? null,
        ], $page['items']), $offset, $page['total']);
    }

    /** @return array<string, mixed> */
    private function analytics(Caller $caller, ToolInput $input): array
    {
        $questionnaire = $this->reported->get($caller, $input->uuid('questionnaire_id'));
        $data = $this->dashboards->data($questionnaire);
        $this->events->publish(AnalyticsFetched::of($questionnaire->customerId(), $questionnaire->id(), 'analytics'));

        return [
            'questionnaire_id' => $questionnaire->id(),
            'title' => $questionnaire->title(),
            'sessions' => $data['sessions'],
            'questions' => \array_slice($data['questions'], 0, 30),
            'tiers' => $data['tiers'],
        ];
    }

    /**
     * The questionnaire, when the caller may see it (their account, or any as Admin); another account's is 404.
     *
     * @return array<string, mixed>
     */
    private function owned(Caller $caller, string $id): array
    {
        $questionnaire = $this->details->find($id);
        if (null === $questionnaire || !$caller->owns((string) $questionnaire['customer_id'])) {
            throw new NotFound('QUESTIONNAIRE_NOT_FOUND', 'The questionnaire does not exist.');
        }

        return $questionnaire;
    }
}
