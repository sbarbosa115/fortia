<?php

namespace App\Responses\Application\Port;

/**
 * Real-time speech transcription (PRD §13.4): an ephemeral secret (~1 minute) the browser uses for one recording.
 * Adapters: the browser's own Web Speech API (TRANSCRIPTION_PROVIDER=browser, no secret needed) and the OpenAI
 * Realtime API (TRANSCRIPTION_PROVIDER=openai), billed to the account's own OpenAI key or the platform's
 * OPENAI_API_KEY; without either key it falls back to the browser.
 */
interface TranscriptionTokens
{
    /** @throws \App\Shared\Domain\Error\UpstreamFailed when the provider does not issue one */
    public function issue(?string $customerId = null): TranscriptionToken;
}
