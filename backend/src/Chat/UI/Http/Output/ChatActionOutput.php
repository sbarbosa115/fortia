<?php

namespace App\Chat\UI\Http\Output;

use OpenApi\Attributes as OA;

/**
 * A queued change after the user answered (PRD §7.19 "actions"): done, failed or declined. A styles change carries
 * the job_id of the styles job; a language change the new language.
 */
final class ChatActionOutput
{
    /** @param array<string, mixed>|null $result */
    public function __construct(
        public readonly string $id,
        public readonly string $tool,
        public readonly string $label,
        #[OA\Property(enum: ['done', 'failed', 'declined'])]
        public readonly string $status,
        public readonly ?ChatErrorOutput $error = null,
        public readonly ?string $job_id = null,
        #[OA\Property(enum: ['es', 'en'], nullable: true)]
        public readonly ?string $language = null,
        public readonly ?string $url = null,
        #[OA\Property(type: 'object', nullable: true, additionalProperties: true)]
        public readonly ?array $result = null,
    ) {
    }
}
