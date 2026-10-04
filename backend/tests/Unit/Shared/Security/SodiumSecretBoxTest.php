<?php

namespace App\Tests\Unit\Shared\Security;

use App\Shared\Application\Security\SecretNotReadable;
use App\Shared\Infrastructure\Security\SodiumSecretBox;
use PHPUnit\Framework\TestCase;

final class SodiumSecretBoxTest extends TestCase
{
    public function testASealedSecretOpensToTheSameTextAndIsNotStoredInClear(): void
    {
        $box = new SodiumSecretBox('a-test-key');

        $sealed = $box->seal('sk-proj-secret-1234');

        self::assertStringNotContainsString('sk-proj-secret-1234', $sealed, 'secrets are stored encrypted, never in clear');
        self::assertSame('sk-proj-secret-1234', $box->open($sealed));
    }

    public function testTheSameSecretSealsDifferentlyEachTime(): void
    {
        $box = new SodiumSecretBox('a-test-key');

        self::assertNotSame($box->seal('password'), $box->seal('password'), 'a random nonce per seal: equal secrets do not look equal');
    }

    public function testAnotherKeyCannotOpenIt(): void
    {
        $sealed = (new SodiumSecretBox('a-test-key'))->seal('password');

        $this->expectException(SecretNotReadable::class);
        (new SodiumSecretBox('another-key'))->open($sealed);
    }

    public function testATamperedValueIsRefused(): void
    {
        $box = new SodiumSecretBox('a-test-key');
        $sealed = $box->seal('password');
        $tampered = substr($sealed, 0, -4).('AAAA' === substr($sealed, -4) ? 'BBBB' : 'AAAA');

        $this->expectException(SecretNotReadable::class);
        $box->open($tampered);
    }

    public function testAnEmptyKeyIsAConfigurationError(): void
    {
        $this->expectException(\LogicException::class);
        new SodiumSecretBox('');
    }
}
