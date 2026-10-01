<?php

namespace App\Identity\UI\Http\Output;

use OpenApi\Attributes as OA;

/** D15: the workspace described in onboarding step 2. */
final class WorkspaceOutput
{
    public function __construct(
        public readonly ?string $name,
        #[OA\Property(enum: ['es-CO', 'en-US'])]
        public readonly string $language,
        public readonly ?string $website,
    ) {
    }
}
