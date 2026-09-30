<?php

namespace App\Responses\Application\Port;

/**
 * Real-time speech transcription (PRD §13.4): an ephemeral secret (~1 minute) the browser uses for one recording.
 * Adapters: the browser's own Web Speech API (TRANSCRIPTION_PROVIDER=browser, no secret needed) and the OpenAI
 * Realtime API (TRANSCRIPTION_PROVIDER=openai with OPENAI_API_KEY).
 */
interface TranscriptionTokens
{
    /** @throws \App\Shared\Domain\Error\UpstreamFailed when the provider does not issue one */
    public function issue(): TranscriptionToken;
}
