<?php

namespace App\Shared\Infrastructure\Security;

use App\Shared\Application\Security\SecretBox;
use App\Shared\Application\Security\SecretNotReadable;

/**
 * libsodium's secretbox (XSalsa20-Poly1305): authenticated, with a random nonce per value. The 32-byte key is
 * derived from SETTINGS_ENCRYPTION_KEY with BLAKE2b, so any long random string works as the setting. Stored as
 * base64(nonce . ciphertext).
 */
final class SodiumSecretBox implements SecretBox
{
    private readonly string $key;

    public function __construct(#[\SensitiveParameter] string $encryptionKey)
    {
        if ('' === $encryptionKey) {
            throw new \LogicException('SETTINGS_ENCRYPTION_KEY is not set.');
        }
        $this->key = sodium_crypto_generichash($encryptionKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function seal(#[\SensitiveParameter] string $secret): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($secret, $nonce, $this->key));
    }

    public function open(string $sealed): string
    {
        $raw = base64_decode($sealed, true);
        if (false === $raw || \strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new SecretNotReadable('The stored secret is not a sealed value.');
        }
        $secret = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key,
        );
        if (false === $secret) {
            throw new SecretNotReadable('The stored secret cannot be opened with SETTINGS_ENCRYPTION_KEY.');
        }

        return $secret;
    }
}
