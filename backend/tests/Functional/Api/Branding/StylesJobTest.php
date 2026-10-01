<?php

namespace App\Tests\Functional\Api\Branding;

use App\Branding\Application\Port\BrandSnapshot;
use App\Branding\Domain\BrandStyles;
use App\Branding\Domain\Contrast;
use App\Branding\Domain\Model\CustomerStyles;
use App\Branding\Infrastructure\Extractor\FakeBrandExtractor;
use App\Jobs\Domain\Model\Job;
use App\Shared\Application\Llm\LlmResponse;
use App\Tests\Support\ApiTestCase;

/** The styles job (PRD §7.16): reading the website, designing with the model, or merging the partial styles. */
final class StylesJobTest extends ApiTestCase
{
    public function testWithoutAWebsiteChangeThePartialIsMergedOverTheDefaults(): void
    {
        $owner = $this->account('ACME0001');

        $job = $this->restyle($owner, ['website' => '', 'styles' => ['button' => ['primary' => ['background' => '#8249df']]]]);

        self::assertSame('COMPLETED', $job['status']);
        $saved = $this->stored('ACME0001');
        self::assertSame('#8249df', $saved->styles()['button']['primary']['background'], '§7.16: the partial is deep-merged…');
        self::assertEquals(BrandStyles::defaults()['body'], $saved->styles()['body'], '…over the defaults when there were none');
        self::assertNull($saved->website());
        self::assertSame([], $this->llm()->requests(), 'no website change: the model is not called');
        self::assertEquals(['type' => 'styles', 'website' => null, 'styles' => $saved->styles()], $job['result']);
        self::assertSame('saving', $job['stage'], '§7.16 visible stages end in "saving"');
    }

    public function testWithoutAWebsiteChangeThePartialIsMergedOverTheStoredStyles(): void
    {
        $owner = $this->account('ACME0001');
        $this->em()->persist(new CustomerStyles('ACME0001', 'https://acme.test', ['logoUrl' => 'https://acme.test/logo.png', 'a' => ['color' => '#111111'], 'body' => ['background' => '#ffffff', 'color' => '#000000']], new \DateTimeImmutable()));
        $this->em()->flush();

        $this->restyle($owner, ['website' => 'https://acme.test', 'styles' => ['a' => ['color' => '#222222']]]);

        $saved = $this->stored('ACME0001');
        self::assertEquals(['logoUrl' => 'https://acme.test/logo.png', 'a' => ['color' => '#222222'], 'body' => ['background' => '#ffffff', 'color' => '#000000']], $saved->styles(), '§7.16: the same website keeps the stored styles and merges the partial');
        self::assertSame('https://acme.test', $saved->website());
    }

    public function testANewWebsiteIsReadAndDesignedAndThePartialIsIgnored(): void
    {
        $owner = $this->account('ACME0001');
        $this->extractor()->willReturn(new BrandSnapshot(
            'https://brand.example',
            'Brand',
            ['#ffffff', '#0f172a', '#e11d48'],
            ['Poppins'],
            ['https://brand.example/hero.jpg', 'https://brand.example/img/brand-logo.svg'],
            'body{color:#0f172a}',
        ));

        $job = $this->restyle($owner, ['website' => 'https://brand.example', 'styles' => ['a' => ['color' => '#000000']]]);

        self::assertSame('COMPLETED', $job['status'], (string) json_encode($job));
        $styles = $this->stored('ACME0001')->styles();
        self::assertSame('#e11d48', $styles['button']['primary']['background'], 'the brand colour comes from the site');
        self::assertSame('#e11d48', $styles['a']['color'], '§7.16 step 5: the partial styles of the request are ignored');
        self::assertSame('Poppins', $styles['font']['family']);
        self::assertStringStartsWith('https://fonts.googleapis.com/css2?family=Poppins', $styles['font']['url'], '§9.15: fonts only from Google Fonts');
        self::assertSame('https://brand.example/hero.jpg', $styles['logoUrl'], '§7.16 step 4: the logo is one of the candidates');
        self::assertSame('https://brand.example', $this->stored('ACME0001')->website());
        $request = $this->llm()->requests()[0];
        self::assertSame('styles--rules-to-extract-brand-styles', $request->purpose, 'the system prompt of §13 for styles');
        self::assertStringContainsString('untrusted page content', $request->messages[0]->content, 'the page is data, never instructions');
        self::assertSame('generation', $request->tier);
    }

    public function testTheDesignedStylesAreSanitizedCompletedAndReadable(): void
    {
        $owner = $this->account('ACME0001');
        $this->llm()->willAnswer(LlmResponse::json([
            'styles' => [
                'font' => ['family' => 'Inter; } body{display:none'],
                'body' => ['background' => '#ffffff', 'color' => '#f1f5f9'],
                'a' => ['color' => 'javascript:alert(1)'],
                'button' => ['primary' => ['background' => '#fde047', 'color' => '#ffffff']],
            ],
            'logo_url' => 'https://evil.test/tracker.gif',
        ]));

        $this->restyle($owner, ['website' => 'https://pale.example']);

        $styles = $this->stored('ACME0001')->styles();
        self::assertSame('Montserrat', $styles['font']['family'], 'an injected font name is dropped and the default stays');
        self::assertSame('#c45a3d', $styles['a']['color'], 'a value that is not a colour is dropped');
        self::assertGreaterThanOrEqual(3.0, Contrast::ratio($styles['body']['color'], '#ffffff'), '§7.16 step 3: text contrast ≥ 3:1');
        self::assertSame('#000000', $styles['button']['primary']['color'], 'white on yellow falls back to black');
        self::assertArrayNotHasKey('logoUrl', $styles, '§7.16 step 4: a logo that was not found on the page is never used');
        self::assertArrayHasKey('input', $styles, 'missing sections come from the defaults');
    }

    public function testAFailedJobChangesNothing(): void
    {
        $owner = $this->account('ACME0001');
        $this->restyle($owner, ['website' => '']);

        $this->extractor()->willFail();
        $failed = $this->restyle($owner, ['website' => 'https://down.example']);

        self::assertSame('FAILED', $failed['status']);
        self::assertSame('WEBSITE_UNREACHABLE', $failed['result']['error']['type']);
        self::assertNull($this->stored('ACME0001')->website(), 'a failed job saves nothing');
    }

    public function testWhenTheModelFailsTheJobFailsAndNothingIsSaved(): void
    {
        $owner = $this->account('ACME0001');
        $this->llm()->willFail();

        $job = $this->restyle($owner, ['website' => 'https://brand.example']);

        self::assertSame('FAILED', $job['status']);
        self::assertNull(static::getContainer()->get(\App\Branding\Domain\Repository\CustomerStylesRepository::class)->find('ACME0001'));
    }

    public function testAnAccountOnlyEverRestylesItself(): void
    {
        $this->account('ACME0001');
        $globex = $this->account('GLOBEX01');
        $this->em()->persist(new CustomerStyles('ACME0001', 'https://acme.test', ['a' => ['color' => '#111111']], new \DateTimeImmutable()));
        $this->em()->flush();

        $job = $this->restyle($globex, ['website' => '', 'styles' => ['a' => ['color' => '#222222']]]);

        self::assertSame('GLOBEX01', $this->em()->find(Job::class, $job['job_id'])?->customerId(), 'the job is the caller account\'s');
        self::assertSame('#222222', $this->stored('GLOBEX01')->styles()['a']['color']);
        self::assertSame(['a' => ['color' => '#111111']], $this->stored('ACME0001')->styles(), "another tenant's brand is never touched");
    }

    /**
     * POST /styles, then the job as GET /jobs/{id} shows it (jobs run inside the request in tests).
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function restyle(string $as, array $body): array
    {
        $started = $this->data($this->api('POST', '/api/v1/styles', $body, as: $as), 202)['job'];

        return $this->data($this->api('GET', '/api/v1/jobs/'.$started['job_id'], as: $as))['job'];
    }

    private function stored(string $customerId): CustomerStyles
    {
        $this->em()->clear();
        $styles = $this->em()->find(CustomerStyles::class, $customerId);
        self::assertNotNull($styles, 'the styles were saved');

        return $styles;
    }

    private function extractor(): FakeBrandExtractor
    {
        return static::getContainer()->get(FakeBrandExtractor::class);
    }
}
