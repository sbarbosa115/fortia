<?php

namespace App\Responses\Application\Port;

final class TranscriptionToken
{
    /**
     * @param string $provider  "browser" (the app transcribes with the Web Speech API) or "openai"
     * @param int    $expiresIn seconds
     */
    public function __construct(
        public readonly string $token,
        public readonly string $provider,
        public readonly int $expiresIn,
    ) {
    }
}
