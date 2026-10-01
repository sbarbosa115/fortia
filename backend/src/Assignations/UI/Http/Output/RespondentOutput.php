<?php

namespace App\Assignations\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** A member of the audience and how far they got (PRD §8.8 respondents). */
final class RespondentOutput
{
    /** @param list<RespondentAttemptOutput> $attempts_detail */
    public function __construct(
        public readonly string $organization_user_id,
        public readonly string $organization_user_name,
        public readonly ?string $organization_user_email,
        #[OA\Property(enum: ['pending', 'in_progress', 'completed'])]
        public readonly string $status,
        public readonly ?string $session_id,
        public readonly int $completed_stages,
        public readonly int $total_stages,
        public readonly int $attempts,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: RespondentAttemptOutput::class)))]
        public readonly array $attempts_detail,
    ) {
    }

    /** @param array<string, mixed> $r */
    public static function of(array $r): self
    {
        /** @var list<array<string, mixed>> $attempts */
        $attempts = $r['attempts_detail'];

        return new self(
            (string) $r['organization_user_id'],
            (string) $r['organization_user_name'],
            null === $r['organization_user_email'] ? null : (string) $r['organization_user_email'],
            (string) $r['status'],
            null === $r['session_id'] ? null : (string) $r['session_id'],
            (int) $r['completed_stages'],
            (int) $r['total_stages'],
            (int) $r['attempts'],
            array_map(static fn (array $a): RespondentAttemptOutput => new RespondentAttemptOutput(
                (int) $a['number'],
                (string) $a['session_id'],
                (string) $a['status'],
                null === $a['started_at'] ? null : (string) $a['started_at'],
                null === $a['ended_at'] ? null : (string) $a['ended_at'],
            ), $attempts),
        );
    }
}
