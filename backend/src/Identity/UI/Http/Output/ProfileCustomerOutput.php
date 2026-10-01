<?php

namespace App\Identity\UI\Http\Output;

use OpenApi\Attributes as OA;

/** PRD §8.2 GET /profile "customer": the account, the signed-in user and the brand. */
final class ProfileCustomerOutput
{
    /** @param array<string, mixed>|null $styles */
    public function __construct(
        public readonly string $customer_id,
        public readonly string $name,
        public readonly string $email,
        #[OA\Property(enum: ['es-CO', 'en-US'])]
        public readonly string $language,
        public readonly ?string $logo_url,
        public readonly ?string $website,
        #[OA\Property(type: 'object', nullable: true, additionalProperties: true)]
        public readonly ?array $styles,
    ) {
    }
}
