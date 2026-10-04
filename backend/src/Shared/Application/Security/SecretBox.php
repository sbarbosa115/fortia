<?php

namespace App\Shared\Application\Security;

/**
 * Encrypts the secrets an account stores (its SMTP password, its OpenAI API key) with the platform key
 * SETTINGS_ENCRYPTION_KEY. What is stored is the sealed text; only the server can open it.
 */
interface SecretBox
{
    public function seal(#[\SensitiveParameter] string $secret): string;

    /** @throws SecretNotReadable when the value was sealed with another key or was altered */
    public function open(string $sealed): string;
}
