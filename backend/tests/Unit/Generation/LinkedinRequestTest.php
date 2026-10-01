<?php

namespace App\Tests\Unit\Generation;

use App\Generation\Domain\LinkedinRequest;
use PHPUnit\Framework\TestCase;

final class LinkedinRequestTest extends TestCase
{
    public function testOnlyLinkedinProfileUrlsAreAccepted(): void
    {
        foreach (['https://www.linkedin.com/in/ana-perez', 'http://linkedin.com/in/ana-perez/', 'https://co.linkedin.com/in/ana-perez?trk=x', 'https://es.www.linkedin.com/in/ana'] as $url) {
            self::assertTrue(LinkedinRequest::isProfileUrl($url), "§8.4: $url is a profile URL");
        }
        foreach (['https://www.linkedin.com/company/acme', 'https://evil.test/in/ana', 'https://linkedin.com.evil.test/in/ana', 'ftp://linkedin.com/in/ana', 'https://www.linkedin.com/in/', 'linkedin.com/in/ana'] as $url) {
            self::assertFalse(LinkedinRequest::isProfileUrl($url), "§8.4: $url is not a profile URL");
        }
    }

    public function testTheHandleIsTheSegmentAfterIn(): void
    {
        self::assertSame('ana-perez', LinkedinRequest::handle('https://www.linkedin.com/in/ana-perez/?trk=1'));
    }

    public function testAnyLanguageOtherThanEnglishBecomesSpanish(): void
    {
        self::assertSame('en', LinkedinRequest::language('en'));
        self::assertSame('es', LinkedinRequest::language('es'));
        self::assertSame('es', LinkedinRequest::language('fr'), '§8.4: any other value becomes es');
        self::assertSame('es', LinkedinRequest::language(null));
    }
}
