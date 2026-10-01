<?php

namespace App\Reporting\UI\Http\Controller;

use App\Billing\Application\Features;
use App\Billing\Application\PlanGate;
use App\Reporting\Application\Command\GenerateDashboard;
use App\Reporting\Application\Query\DashboardQueries;
use App\Reporting\Application\Query\ReportedQuestionnaires;
use App\Reporting\Domain\Event\AnalyticsFetched;
use App\Reporting\UI\Http\Output\AnalyticsOutput;
use App\Reporting\UI\Http\Output\DashboardDataOutput;
use App\Reporting\UI\Http\Output\DashboardOutput;
use App\Reporting\UI\Http\Output\QuestionStatsOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Security\Caller;
use App\Shared\UI\Http\Request\RouteId;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** The dashboard and analytics of a questionnaire (PRD §7.10, §8.4, §10.9): Cap(analytics), owner or Admin. */
#[OA\Tag(name: 'Reporting')]
final class DashboardController
{
    public function __construct(
        private readonly ReportedQuestionnaires $questionnaires,
        private readonly DashboardQueries $dashboards,
        private readonly PlanGate $gate,
        private readonly CommandBus $commands,
        private readonly EventBus $events,
    ) {
    }

    /**
     * The dashboard layout. The first request has the LLM choose it (once, stored forever; counts one "dashboards");
     * without a stored dashboard and without dashboards capacity it answers 200 with locked and no charts.
     */
    #[Route('/questionnaire/{id}/dashboard', name: 'api_questionnaire_dashboard', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The dashboard, or locked', content: new Model(type: DashboardOutput::class))]
    #[OA\Response(response: 404, description: 'QUESTIONNAIRE_NOT_FOUND')]
    #[OA\Response(response: 429, description: 'PLAN_LIMIT_REACHED (analytics)')]
    #[OA\Response(response: 502, description: 'DASHBOARD_GENERATION_FAILED: nothing was stored')]
    public function dashboard(string $id, Caller $caller): JsonResponse
    {
        $questionnaire = $this->questionnaires->get($caller, RouteId::uuid($id));
        $this->gate->capacity($caller, Features::ANALYTICS);

        $stored = $this->dashboards->stored($questionnaire->id());
        $locked = null;
        if (null === $stored) {
            $locked = $this->dashboards->lockFor($caller);
            if (null === $locked) {
                $this->commands->dispatch(new GenerateDashboard($questionnaire->id()));
                $stored = $this->dashboards->stored($questionnaire->id());
            }
        }

        return ApiResponse::ok(DashboardOutput::of(
            $questionnaire->id(),
            $questionnaire->customerId(),
            $questionnaire->title(),
            $stored,
            ReportedQuestionnaires::profiles($questionnaire),
            $locked,
        ));
    }

    /** The data behind the charts (PRD §10.9), computed from the stored sessions. Counts one "analytics". */
    #[Route('/questionnaire/{id}/dashboard/data', name: 'api_questionnaire_dashboard_data', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The dashboard data', content: new Model(type: DashboardDataOutput::class))]
    #[OA\Response(response: 404, description: 'QUESTIONNAIRE_NOT_FOUND')]
    #[OA\Response(response: 429, description: 'PLAN_LIMIT_REACHED (analytics)')]
    #[OA\Response(response: 502, description: 'ANALYTICS_UNAVAILABLE')]
    public function data(string $id, Caller $caller): JsonResponse
    {
        $questionnaire = $this->questionnaires->get($caller, RouteId::uuid($id));
        $this->gate->capacity($caller, Features::ANALYTICS);

        $data = $this->dashboards->data($questionnaire);
        $this->events->publish(AnalyticsFetched::of($questionnaire->customerId(), $questionnaire->id(), 'dashboard_data'));

        return ApiResponse::ok(DashboardDataOutput::of($data));
    }

    /** The general analytics of a questionnaire (PRD §8.4). Counts one "analytics" (AnalyticsFetched). */
    #[Route('/questionnaire/{id}/analytics', name: 'api_questionnaire_analytics', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The analytics', content: new Model(type: AnalyticsOutput::class))]
    #[OA\Response(response: 404, description: 'QUESTIONNAIRE_NOT_FOUND')]
    #[OA\Response(response: 429, description: 'PLAN_LIMIT_REACHED (analytics)')]
    #[OA\Response(response: 502, description: 'ANALYTICS_UNAVAILABLE')]
    public function analytics(string $id, Caller $caller): JsonResponse
    {
        $questionnaire = $this->questionnaires->get($caller, RouteId::uuid($id));
        $this->gate->capacity($caller, Features::ANALYTICS);

        $data = $this->dashboards->data($questionnaire);
        $this->events->publish(AnalyticsFetched::of($questionnaire->customerId(), $questionnaire->id(), 'analytics'));

        return ApiResponse::ok(new AnalyticsOutput(
            $questionnaire->id(),
            QuestionStatsOutput::list($data['questions']),
            (int) $data['sessions']['total'],
            (int) $data['sessions']['completed'],
        ));
    }
}
