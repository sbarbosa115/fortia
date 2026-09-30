<?php

namespace App\Responses\UI\Http\Output;

use OpenApi\Attributes as OA;

/**
 * An ephemeral secret for one recording (PRD §8.4 GET /transcription/token, §13.4). `provider` tells the app which
 * transport to use: "browser" (the Web Speech API; the token is only a marker) or "openai" (the Realtime API).
 */
final class TranscriptionTokenOutput
{
    public function __construct(
        public readonly string $token,
        #[OA\Property(enum: ['browser', 'openai'])]
        public readonly string $provider,
        public readonly int $expires_in,
    ) {
    }
}
