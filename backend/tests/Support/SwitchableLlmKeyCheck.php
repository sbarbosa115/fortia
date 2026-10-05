<?php

namespace App\Tests\Support;

use App\Shared\Application\Llm\LlmKeyCheck;

/** The key check of the test container: every account has a key until a test takes them away. */
final class SwitchableLlmKeyCheck implements LlmKeyCheck
{
    private bool $missing = false;

    public function missingKey(?string $customerId): bool
    {
        return $this->missing;
    }

    public function withoutKeys(): void
    {
        $this->missing = true;
    }
}
