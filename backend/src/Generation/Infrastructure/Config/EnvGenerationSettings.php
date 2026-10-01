<?php

namespace App\Generation\Infrastructure\Config;

use App\Generation\Application\GenerationSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** GenerationSettings from the environment: LINKEDIN_OWNER_CUSTOMER_ID (D8). */
final class EnvGenerationSettings implements GenerationSettings
{
    public function __construct(
        #[Autowire(env: 'LINKEDIN_OWNER_CUSTOMER_ID')]
        private readonly string $linkedinOwnerCustomerId,
    ) {
    }

    public function linkedinOwnerCustomerId(): ?string
    {
        $owner = trim($this->linkedinOwnerCustomerId);

        return '' === $owner ? null : $owner;
    }
}
