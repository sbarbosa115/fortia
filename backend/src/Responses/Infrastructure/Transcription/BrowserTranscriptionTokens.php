<?php

namespace App\Responses\Infrastructure\Transcription;

use App\Responses\Application\Port\TranscriptionToken;
use App\Responses\Application\Port\TranscriptionTokens;

/**
 * The browser transcribes with its own Web Speech API: nothing to authorize, so the token is only an opaque
 * one-recording marker and the provider tells the app which transport to use.
 */
final class BrowserTranscriptionTokens implements TranscriptionTokens
{
    public const PROVIDER = 'browser';

    public function issue(): TranscriptionToken
    {
        return new TranscriptionToken('browser.'.bin2hex(random_bytes(16)), self::PROVIDER, 60);
    }
}
