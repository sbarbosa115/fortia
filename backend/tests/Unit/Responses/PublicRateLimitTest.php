<?php

namespace App\Tests\Unit\Responses;

use App\Responses\UI\Http\PublicRateLimit;
use App\Shared\Domain\Error\TooManyRequests;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/** D4, D24: the public respondent endpoints are rate limited per client IP with the public_api limiter. */
final class PublicRateLimitTest extends TestCase
{
    public function testAClientOverTheLimitGets429TooManyAttemptsAndOtherClientsDoNot(): void
    {
        $limit = new PublicRateLimit(new RateLimiterFactory(['id' => 'public_api', 'policy' => 'sliding_window', 'limit' => 2, 'interval' => '1 minute'], new InMemoryStorage()));
        $client = Request::create('/api/v1/transcription/token', server: ['REMOTE_ADDR' => '10.0.0.1']);
        $other = Request::create('/api/v1/transcription/token', server: ['REMOTE_ADDR' => '10.0.0.2']);

        $limit->consume($client);
        $limit->consume($client);
        $limit->consume($other);

        try {
            $limit->consume($client);
            self::fail('D24: the third request within the window must be refused');
        } catch (TooManyRequests $e) {
            self::assertSame('TOO_MANY_ATTEMPTS', $e->errorCode(), 'the refusal carries the 429 code the UI reads');
        }
    }
}
