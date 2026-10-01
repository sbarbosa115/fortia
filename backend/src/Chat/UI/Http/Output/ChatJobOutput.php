<?php

namespace App\Chat\UI\Http\Output;

use OpenApi\Attributes as OA;

/** The `chat` job as POST /chat and GET /jobs/{id} return it; its result, once COMPLETED, is a ChatTurnResultOutput. */
final class ChatJobOutput
{
    public function __construct(
        public readonly string $job_id,
        #[OA\Property(enum: ['chat'])]
        public readonly string $job_type,
        #[OA\Property(enum: ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED', 'CANCELLED'])]
        public readonly string $status,
        public readonly ?ChatTurnResultOutput $result,
        public readonly ?string $stage,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {
    }
}
