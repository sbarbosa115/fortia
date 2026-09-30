<?php

namespace App\Responses\Infrastructure\Llm;

use App\Responses\Application\Job\ProductRecommendationJob;
use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmResponse;
use App\Shared\Infrastructure\Llm\Fake\FakeLlmResponder;

/** Offline product recommendation (PRD §7.7): the first three products of the catalog, in catalog order. */
final class ProductRecommendationResponder implements FakeLlmResponder
{
    public function supports(LlmRequest $request): bool
    {
        return ProductRecommendationJob::PURPOSE === $request->purpose;
    }

    public function respond(LlmRequest $request): LlmResponse
    {
        $ids = array_values(array_map('strval', (array) ($request->context['catalog_ids'] ?? [])));

        return LlmResponse::json(['product_ids' => \array_slice($ids, 0, 3)]);
    }
}
