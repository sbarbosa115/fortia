<?php

namespace App\Tests\Unit\Shared\Llm;

use App\Shared\Application\Llm\OpenAiKeys;
use App\Shared\Infrastructure\Llm\ProviderLlmKeyCheck;
use PHPUnit\Framework\TestCase;

final class ProviderLlmKeyCheckTest extends TestCase
{
    private function check(string $provider): ProviderLlmKeyCheck
    {
        return new ProviderLlmKeyCheck(new class implements OpenAiKeys {
            public function keyFor(?string $customerId): string
            {
                return 'ACME0001' === $customerId ? 'sk-acme' : '';
            }
        }, $provider);
    }

    public function testTheRealProvidersLackAKeyWhenNeitherTheAccountNorThePlatformHasOne(): void
    {
        self::assertTrue($this->check('auto')->missingKey('GLOBEX01'), 'auto without a key would answer with the offline fake');
        self::assertFalse($this->check('auto')->missingKey('ACME0001'), 'the account\'s own key is enough');
        self::assertFalse($this->check(' AUTO ')->missingKey('ACME0001'));
        self::assertFalse($this->check('fake')->missingKey('GLOBEX01'), 'the fake on purpose (tests, demos) needs no key');
        self::assertTrue($this->check('openai')->missingKey('GLOBEX01'), 'openai without a key would fail');
        self::assertFalse($this->check('openai')->missingKey('ACME0001'));
    }
}
