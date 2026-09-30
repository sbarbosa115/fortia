<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Llm;

use App\Shared\Application\Llm\LlmRequest;

/** Which model serves each tier (LLM_MODEL_GENERATION, LLM_MODEL_FAST). */
final class ModelCatalog
{
    public function __construct(
        private readonly string $generationModel,
        private readonly string $fastModel,
    ) {
    }

    public function forTier(string $tier): string
    {
        return LlmRequest::TIER_FAST === $tier ? $this->fastModel : $this->generationModel;
    }

    /** The effort setting exists on Opus, Sonnet and Fable models, not on Haiku. */
    public function supportsEffort(string $model): bool
    {
        return !str_contains($model, 'haiku');
    }
}
