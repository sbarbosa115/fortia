<?php

namespace App\Generation\Application;

/**
 * The generation context's configuration (D8: client-specific logic as configuration, not code).
 */
interface GenerationSettings
{
    /** The account that owns the questionnaires generated from LinkedIn (PRD §7.18); null when not configured. */
    public function linkedinOwnerCustomerId(): ?string;
}
