<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Llm\Fake;

use App\Shared\Application\Llm\LanguageModel;
use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmResponse;
use App\Shared\Application\Llm\LlmUnavailable;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The offline language model: the first responder that supports the request answers it. Tests can also queue
 * answers (willAnswer / willFail) to drive a specific case.
 */
final class FakeLanguageModel implements LanguageModel
{
    /** @var list<LlmResponse|\Throwable> */
    private array $queued = [];

    /** @var list<LlmRequest> */
    private array $requests = [];

    /** @param iterable<FakeLlmResponder> $responders */
    public function __construct(
        #[AutowireIterator('app.fake_llm_responder')]
        private readonly iterable $responders,
    ) {
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        $this->requests[] = $request;
        if ([] !== $this->queued) {
            $next = array_shift($this->queued);
            if ($next instanceof \Throwable) {
                throw $next;
            }

            return $next;
        }
        foreach ($this->responders as $responder) {
            if ($responder->supports($request)) {
                return $responder->respond($request);
            }
        }

        throw new LlmUnavailable(\sprintf('The fake language model has no responder for "%s".', $request->purpose));
    }

    /** Tests: the next call returns this. */
    public function willAnswer(LlmResponse $response): void
    {
        $this->queued[] = $response;
    }

    /** Tests: the next call fails. */
    public function willFail(?\Throwable $error = null): void
    {
        $this->queued[] = $error ?? new LlmUnavailable('Fake failure.');
    }

    /** @return list<LlmRequest> */
    public function requests(): array
    {
        return $this->requests;
    }
}
