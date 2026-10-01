<?php

namespace App\Reporting\UI\Http\Output;

use App\Shared\UI\Http\Output\Document\CtaOutput;
use App\Shared\UI\Http\Output\Document\DiagnosticResultOutput;
use App\Shared\UI\Http\Output\Document\ProductOutput;
use App\Shared\UI\Http\Output\Document\SessionResultsOutput;

/**
 * One response of a questionnaire with its results (PRD §10.8 answer detail): what the console shows when the
 * session chain is not available (403/404).
 */
final class AnswerDetailOutput
{
    public function __construct(
        public readonly AnswerOutput $session,
        public readonly ?SessionResultsOutput $results,
    ) {
    }

    /** @param array{session: array<string, mixed>, member: array{organization_user_id: string, name: string, email: string|null, phone: string|null}|null, results: array<string, mixed>|null, flow: array<string, mixed>|null} $detail */
    public static function of(array $detail): self
    {
        $session = AnswerOutput::of($detail['session'], $detail['member'], null);
        $results = $detail['results'];
        $flow = $detail['flow'] ?? [];

        return new self($session, null === $results ? null : new SessionResultsOutput(
            $session->session_id,
            $session->customer_id,
            $session->questionnaire_id,
            CtaOutput::fromArray(\is_array($flow['cta'] ?? null) ? $flow['cta'] : null),
            \is_array($flow['layout'] ?? null) ? array_values(array_map('strval', $flow['layout'])) : null,
            \is_array($flow['result_copy'] ?? null) ? array_map('strval', $flow['result_copy']) : null,
            \is_array($results['products'] ?? null) ? array_map(static fn (array $p): ProductOutput => ProductOutput::fromArray($p), array_values(array_filter($results['products'], 'is_array'))) : null,
            \is_array($results['ai_team_profile'] ?? null) ? $results['ai_team_profile'] : null,
            \is_array($results['diagnostic'] ?? null) ? DiagnosticResultOutput::fromArray($results['diagnostic']) : null,
            \is_array($results['extra'] ?? null) ? $results['extra'] : null,
        ));
    }
}
