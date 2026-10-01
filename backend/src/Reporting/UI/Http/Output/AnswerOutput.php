<?php

namespace App\Reporting\UI\Http\Output;

use App\Shared\Domain\Document\QuestionnaireType;
use App\Shared\UI\Http\Output\Document\OnCompletedOutput;
use App\Shared\UI\Http\Output\Document\QuestionOutput;
use App\Shared\UI\Http\Output\Document\SessionOutput;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * A session as the answers listing shows it (PRD §8.4 "sessions enriched with member data"): every field of
 * SessionOutput, plus the organization member who answered it and, with include_chain, the chain stage reached.
 */
final class AnswerOutput
{
    /**
     * @param list<QuestionOutput>                                      $questions
     * @param array{name?: string, email?: string, phone?: string}|null $user_data
     */
    public function __construct(
        public readonly string $session_id,
        public readonly string $questionnaire_id,
        public readonly string $customer_id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $disclaimer,
        public readonly bool $capture_user_data,
        public readonly bool $landing_page,
        #[OA\Property(enum: QuestionnaireType::VALUES)]
        public readonly string $type,
        public readonly bool $is_active,
        public readonly ?OnCompletedOutput $on_completed,
        public readonly string $parent,
        public readonly ?string $slug,
        public readonly ?string $started_at,
        public readonly ?string $ended_at,
        public readonly ?string $flow_id,
        #[OA\Property(enum: ['filling', 'filled_out', 'processing', 'completed'])]
        public readonly string $status,
        #[OA\Property(type: 'object', nullable: true, properties: [new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'email', type: 'string'), new OA\Property(property: 'phone', type: 'string')])]
        public readonly ?array $user_data,
        public readonly ?string $assignations_id,
        public readonly ?string $organization_user_id,
        #[OA\Property(enum: ['follow_up'], nullable: true)]
        public readonly ?string $assignation_type,
        public readonly int $attempt,
        public readonly int $question_count,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: QuestionOutput::class)))]
        public readonly array $questions,
        public readonly ?AnswerMemberOutput $member,
        public readonly ?AnswerChainOutput $chain,
    ) {
    }

    /**
     * @param array<string, mixed>                                                                           $session the session data
     * @param array{organization_user_id: string, name: string, email: string|null, phone: string|null}|null $member
     * @param array{stage: int, total_stages: int}|null                                                      $chain
     */
    public static function of(array $session, ?array $member, ?array $chain): self
    {
        $s = SessionOutput::fromArray($session);

        return new self(
            $s->session_id, $s->questionnaire_id, $s->customer_id, $s->title, $s->description, $s->disclaimer,
            $s->capture_user_data, $s->landing_page, $s->type, $s->is_active, $s->on_completed, $s->parent, $s->slug,
            $s->started_at, $s->ended_at, $s->flow_id, $s->status, $s->user_data, $s->assignations_id,
            $s->organization_user_id, $s->assignation_type, $s->attempt, $s->question_count, $s->questions,
            null === $member ? null : new AnswerMemberOutput($member['organization_user_id'], $member['name'], $member['email'], $member['phone']),
            null === $chain ? null : new AnswerChainOutput($chain['stage'], $chain['total_stages']),
        );
    }
}
