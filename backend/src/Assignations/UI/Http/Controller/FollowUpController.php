<?php

namespace App\Assignations\UI\Http\Controller;

use App\Assignations\Application\Command\NotifyRetry;
use App\Assignations\Application\Command\ReminderOutcome;
use App\Assignations\Application\Command\RetryFollowUp;
use App\Assignations\Application\Command\ReviewAnswer;
use App\Assignations\Application\Command\SendReminder;
use App\Assignations\Application\OwnedAssignations;
use App\Assignations\Application\Query\AssignationDetails;
use App\Assignations\Application\Query\FollowUpStatus;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Assignations\UI\Http\Input\ReviewInput;
use App\Assignations\UI\Http\Output\AnswerReviewedOutput;
use App\Assignations\UI\Http\Output\ReminderSentOutput;
use App\Assignations\UI\Http\Output\RespondentListOutput;
use App\Assignations\UI\Http\Output\RespondentOutput;
use App\Assignations\UI\Http\Output\RetryOutput;
use App\Responses\Application\Query\SessionQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\Rejected;
use App\Shared\UI\Http\Output\Document\ReviewOutput;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Request\RouteId;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.8 the work around an assignation's answers: its respondents, the follow-up reminder (§7.13), the review of
 * each answer and "send for correction" (§7.11). The owner's account or an Admin; another account's is 404 (D3 for
 * retries); writing needs the console's write permission.
 */
#[OA\Tag(name: 'Assignations')]
final class FollowUpController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly AssignationRepository $assignations,
        private readonly OwnedAssignations $owned,
        private readonly AssignationDetails $details,
        private readonly FollowUpStatus $followUps,
        private readonly SessionQueries $sessions,
    ) {
    }

    /** A, a non-owner receives an empty page. The audience by name, with each member's status and attempts. */
    #[Route('/assignations/{id}/respondents', name: 'api_assignations_respondents', methods: ['GET'])]
    #[OA\Parameter(name: 'page_size', in: 'query', description: 'Also "limit"', schema: new OA\Schema(type: 'integer', default: 10, maximum: 100, minimum: 1))]
    #[OA\Parameter(name: 'cursor', in: 'query', description: 'next_cursor of the previous page', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: '{respondents, next_cursor}', content: new Model(type: RespondentListOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_UUID, INVALID_PAGE_SIZE, INVALID_CURSOR')]
    public function respondents(Caller $caller, string $id, Request $request): JsonResponse
    {
        $id = RouteId::uuid($id);
        $raw = (string) ($request->query->get('page_size') ?? $request->query->get('limit') ?? '');
        $pageSize = '' === $raw ? 10 : (1 === preg_match('/^\d{1,3}$/', $raw) ? (int) $raw : 0);
        if ($pageSize < 1 || $pageSize > 100) {
            throw new Rejected('INVALID_PAGE_SIZE', 'page_size must be between 1 and 100.');
        }
        $offset = self::offsetOf($request->query->get('cursor'));
        $assignation = $this->assignations->find($id);
        if (null === $assignation || !$caller->owns($assignation->customerId())) {
            return ApiResponse::ok(new RespondentListOutput([], null));
        }
        $page = $this->details->respondents($assignation, $offset, $pageSize);

        return ApiResponse::ok(new RespondentListOutput(
            array_map(RespondentOutput::of(...), $page['respondents']),
            null === $page['next_offset'] ? null : base64_encode((string) json_encode(['offset' => $page['next_offset']])),
        ));
    }

    /** The manual "Send reminder" (§7.13). */
    #[Route('/assignations/{id}/reminders', name: 'api_assignations_remind', methods: ['POST'])]
    #[OA\Response(response: 200, description: '{recipients}', content: new Model(type: ReminderSentOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_UUID, NOT_A_FOLLOW_UP')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'ASSIGNATION_NOT_FOUND')]
    #[OA\Response(response: 409, description: 'FOLLOW_UP_COMPLETED')]
    #[OA\Response(response: 422, description: 'NO_RECIPIENTS')]
    #[OA\Response(response: 502, description: 'REMINDER_NOT_SENT')]
    public function remind(Caller $caller, string $id): JsonResponse
    {
        $id = RouteId::uuid($id);
        AssignationsController::assertCanWrite($caller);
        /** @var ReminderOutcome $outcome */
        $outcome = $this->commands->dispatch(new SendReminder($id, $caller));

        return ApiResponse::ok(new ReminderSentOutput($outcome->recipients), 'Reminder sent');
    }

    /** Approve or reject one answer of the current attempt of a complete follow-up. */
    #[Route('/assignations/{id}/reviews/{questionId}', name: 'api_assignations_review', methods: ['PUT'])]
    #[OA\RequestBody(content: new Model(type: ReviewInput::class))]
    #[OA\Response(response: 200, description: '{question_id, review, review_status}', content: new Model(type: AnswerReviewedOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR, INVALID_UUID, NOT_A_FOLLOW_UP, QUESTION_LOCKED')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'ASSIGNATION_NOT_FOUND, QUESTION_NOT_FOUND')]
    #[OA\Response(response: 409, description: 'FOLLOW_UP_NOT_COMPLETED')]
    public function review(Caller $caller, string $id, string $questionId, #[Payload(allowExtraFields: false)] ReviewInput $input): JsonResponse
    {
        $id = RouteId::uuid($id);
        AssignationsController::assertCanWrite($caller);
        $this->commands->dispatch(new ReviewAnswer($caller, $id, $questionId, (string) $input->status, $input->comment));

        $assignation = $this->owned->get($caller, $id);
        $review = [];
        foreach ($this->sessions->find((string) $assignation->sharedSessionId())?->questions() ?? [] as $question) {
            if ((string) ($question['id'] ?? '') === $questionId && \is_array($question['review'] ?? null)) {
                $review = $question['review'];
            }
        }

        return ApiResponse::ok(new AnswerReviewedOutput($questionId, ReviewOutput::fromArray($review), $this->followUps->of($assignation)->reviewStatus));
    }

    /**
     * "Send for correction": 201 with the new attempt. With 502 RETRY_EMAIL_NOT_SENT the attempt already exists.
     */
    #[Route('/assignations/{id}/retries', name: 'api_assignations_retry', methods: ['POST'])]
    #[OA\Response(response: 201, description: '{attempt, session_id, recipients}', content: new Model(type: RetryOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_UUID, NOT_A_FOLLOW_UP, NOTHING_TO_RETRY')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'ASSIGNATION_NOT_FOUND')]
    #[OA\Response(response: 409, description: 'FOLLOW_UP_NOT_COMPLETED, REVIEW_INCOMPLETE')]
    #[OA\Response(response: 502, description: 'RETRY_EMAIL_NOT_SENT')]
    public function retry(Caller $caller, string $id): JsonResponse
    {
        $id = RouteId::uuid($id);
        AssignationsController::assertCanWrite($caller);
        /** @var array{attempt: int, session_id: string, rejected: int} $retry */
        $retry = $this->commands->dispatch(new RetryFollowUp($caller, $id));
        // Step 4 after the attempt is committed: a failed email does not undo it (502 RETRY_EMAIL_NOT_SENT).
        $recipients = (int) $this->commands->dispatch(new NotifyRetry($id, $retry['attempt'], $retry['rejected']));

        return ApiResponse::created(new RetryOutput($retry['attempt'], $retry['session_id'], $recipients));
    }

    private static function offsetOf(mixed $cursor): int
    {
        if (null === $cursor || '' === $cursor) {
            return 0;
        }
        $decoded = \is_string($cursor) ? base64_decode($cursor, true) : false;
        $data = false === $decoded ? null : json_decode($decoded, true);
        if (!\is_array($data) || !\is_int($data['offset'] ?? null) || $data['offset'] < 0) {
            throw new Rejected('INVALID_CURSOR', 'The cursor is not valid.');
        }

        return $data['offset'];
    }
}
