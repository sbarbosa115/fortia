<?php

namespace App\Tests\Functional\Api\Content;

use App\Shared\Domain\Ids;
use App\Tests\Support\ApiTestCase;

/** PRD §8.12 GET /videos and §8.13 /admin/videos CRUD (fields of §6.22; Admin only, Customer-Admin gets 403). */
final class VideosApiTest extends ApiTestCase
{
    private const ADMIN_URL = '/api/v1/admin/videos';

    public function testAnAdminCreatesAVideo(): void
    {
        $admin = $this->admin();

        $video = $this->data($this->api('POST', self::ADMIN_URL, $this->body(['title' => 'Create your first questionnaire']), as: $admin), 201);

        self::assertTrue(Ids::isUuid4($video['id']), 'PRD §6.22: id is a UUID');
        self::assertSame('Create your first questionnaire', $video['title']);
        self::assertSame('Step by step.', $video['description']);
        self::assertSame('https://www.youtube.com/watch?v=abcDEF12345', $video['url']);
        self::assertSame('en', $video['language']);
        self::assertSame('getting-started', $video['category']);
        self::assertSame(1, $video['order']);
        self::assertSame(4, $video['duration_minutes']);
        self::assertNotNull($video['created_at']);
        self::assertNotNull($video['updated_at']);
    }

    public function testTheListOfALanguageIsSortedByOrderThenTitle(): void
    {
        $admin = $this->admin();
        $this->create($admin, ['title' => 'Zeta', 'order' => 1]);
        $this->create($admin, ['title' => 'alpha', 'order' => 2]);
        $this->create($admin, ['title' => 'Beta', 'order' => 1]);
        $this->create($admin, ['title' => 'Solo en español', 'order' => 0, 'language' => 'es']);
        $owner = $this->account('ACME0001');

        $videos = $this->data($this->api('GET', '/api/v1/videos?language=en', as: $owner))['videos'];

        self::assertSame(['Beta', 'Zeta', 'alpha'], array_column($videos, 'title'), 'PRD §8.12: sorted by order and then by title, one language');
        $spanish = $this->data($this->api('GET', '/api/v1/videos?language=es', as: $owner))['videos'];
        self::assertSame(['Solo en español'], array_column($spanish, 'title'));
    }

    public function testWithoutALanguageEveryVideoIsListed(): void
    {
        $admin = $this->admin();
        $this->create($admin, ['title' => 'English']);
        $this->create($admin, ['title' => 'Español', 'language' => 'es']);

        $videos = $this->data($this->api('GET', '/api/v1/videos', as: $this->account('ACME0001')))['videos'];

        self::assertCount(2, $videos, 'no ?language = both languages (the console always sends one)');
    }

    public function testAnUnknownLanguageIsInvalidLanguage(): void
    {
        $owner = $this->account('ACME0001');

        $this->assertApiError($this->api('GET', '/api/v1/videos?language=fr', as: $owner), 400, 'INVALID_LANGUAGE', 'PRD §8.12: 400 INVALID_LANGUAGE');
        $this->assertApiError($this->api('GET', '/api/v1/videos?language=', as: $owner), 400, 'INVALID_LANGUAGE', 'PRD §8.12: an empty language is not es|en');
    }

    public function testListingVideosNeedsASignedInUser(): void
    {
        self::assertSame(401, $this->api('GET', '/api/v1/videos?language=es')['status'], 'PRD §8.12: A (authenticated)');
    }

    public function testAReadOnlyUserSeesTheVideos(): void
    {
        $this->create($this->admin(), []);
        $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);

        self::assertCount(1, $this->data($this->api('GET', '/api/v1/videos?language=en', as: 'reader@acme.test'))['videos'], 'PRD §8.12: any signed-in user');
    }

    public function testAnAdminUpdatesAVideoWithAFullReplacement(): void
    {
        $admin = $this->admin();
        $id = $this->create($admin, []);

        $video = $this->data($this->api('PUT', self::ADMIN_URL.'/'.$id, $this->body([
            'title' => 'Renamed',
            'description' => '',
            'url' => 'https://youtu.be/ZYXwvu98765',
            'language' => 'es',
            'category' => 'analytics',
            'order' => 0,
            'duration_minutes' => 0,
        ]), as: $admin));

        self::assertSame($id, $video['id']);
        self::assertSame('Renamed', $video['title']);
        self::assertSame('', $video['description']);
        self::assertSame('https://youtu.be/ZYXwvu98765', $video['url']);
        self::assertSame('es', $video['language']);
        self::assertSame('analytics', $video['category']);
        self::assertSame(0, $video['order']);
        self::assertSame(0, $video['duration_minutes']);
        $this->assertApiError($this->api('PUT', self::ADMIN_URL.'/'.$id, ['title' => 'Only the title'], as: $admin), 400, 'VALIDATION_ERROR', 'PUT is a full replacement: every required field is sent');
    }

    public function testAnAdminReadsOneVideoAndTheWholeList(): void
    {
        $admin = $this->admin();
        $id = $this->create($admin, ['title' => 'One']);
        $this->create($admin, ['title' => 'Two', 'language' => 'es']);

        self::assertSame('One', $this->data($this->api('GET', self::ADMIN_URL.'/'.$id, as: $admin))['title']);
        self::assertCount(2, $this->data($this->api('GET', self::ADMIN_URL, as: $admin))['videos'], 'the admin list has both languages');
        self::assertCount(1, $this->data($this->api('GET', self::ADMIN_URL.'?language=es', as: $admin))['videos']);
        $this->assertApiError($this->api('GET', self::ADMIN_URL.'?language=xx', as: $admin), 400, 'INVALID_LANGUAGE');
    }

    public function testAnAdminDeletesAVideo(): void
    {
        $admin = $this->admin();
        $id = $this->create($admin, []);

        self::assertSame(204, $this->api('DELETE', self::ADMIN_URL.'/'.$id, as: $admin)['status']);

        $this->assertApiError($this->api('GET', self::ADMIN_URL.'/'.$id, as: $admin), 404, 'VIDEO_NOT_FOUND');
        $this->assertApiError($this->api('DELETE', self::ADMIN_URL.'/'.$id, as: $admin), 404, 'VIDEO_NOT_FOUND');
        $this->assertApiError($this->api('PUT', self::ADMIN_URL.'/'.$id, $this->body([]), as: $admin), 404, 'VIDEO_NOT_FOUND');
        self::assertSame([], $this->data($this->api('GET', '/api/v1/videos?language=en', as: $admin))['videos']);
    }

    public function testACustomerAdminAndAReadOnlyUserGetForbiddenOnEveryAdminRoute(): void
    {
        $id = $this->create($this->admin(), []);
        $owner = $this->account('ACME0001');
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);

        foreach ([$owner, 'reader@acme.test'] as $who) {
            $this->assertApiError($this->api('GET', self::ADMIN_URL, as: $who), 403, 'FORBIDDEN', 'PRD §8.13: Admin only, Customer-Admin receives 403');
            $this->assertApiError($this->api('GET', self::ADMIN_URL.'/'.$id, as: $who), 403, 'FORBIDDEN');
            $this->assertApiError($this->api('POST', self::ADMIN_URL, $this->body([]), as: $who), 403, 'FORBIDDEN');
            $this->assertApiError($this->api('PUT', self::ADMIN_URL.'/'.$id, $this->body([]), as: $who), 403, 'FORBIDDEN');
            $this->assertApiError($this->api('DELETE', self::ADMIN_URL.'/'.$id, as: $who), 403, 'FORBIDDEN');
        }
        self::assertSame(401, $this->api('GET', self::ADMIN_URL)['status']);
    }

    public function testAnAdminAssumingACustomerStillUsesTheAdminRoutes(): void
    {
        $admin = $this->admin();
        $this->account('ACME0001');

        $response = $this->api('POST', self::ADMIN_URL, $this->body([]), as: $admin, headers: ['X-Assume-Customer-Id' => 'ACME0001']);

        self::assertSame(201, $response['status'], 'PRD §4.4: /admin/* ignores X-Assume-Customer-Id and uses the real caller — '.$response['body']);
    }

    public function testFieldsOutsideTheirRulesAreValidationErrors(): void
    {
        $admin = $this->admin();

        foreach ([
            'empty title' => ['title' => ''],
            'blank title' => ['title' => '   '],
            'title too long' => ['title' => str_repeat('a', 201)],
            'description too long' => ['description' => str_repeat('a', 2001)],
            'not youtube' => ['url' => 'https://vimeo.com/123456'],
            'url too long' => ['url' => 'https://www.youtube.com/watch?v=abcDEF12345&x='.str_repeat('a', 500)],
            'language' => ['language' => 'fr'],
            'category too long' => ['category' => str_repeat('a', 101)],
            'negative order' => ['order' => -1],
            'negative duration' => ['duration_minutes' => -1],
            'order as text' => ['order' => '1'],
            'extra field' => ['color' => 'red'],
        ] as $case => $override) {
            $response = $this->api('POST', self::ADMIN_URL, $this->body($override), as: $admin);
            $this->assertApiError($response, 400, 'VALIDATION_ERROR', "PRD §6.22: {$case}");
        }
        foreach (['title', 'url', 'language'] as $required) {
            $body = $this->body([]);
            unset($body[$required]);
            $this->assertApiError($this->api('POST', self::ADMIN_URL, $body, as: $admin), 400, 'VALIDATION_ERROR', "{$required} is required");
        }
    }

    public function testOptionalFieldsTakeTheirDefaults(): void
    {
        $video = $this->data($this->api('POST', self::ADMIN_URL, [
            'title' => '  Trimmed  ',
            'url' => 'https://youtu.be/abcDEF12345',
            'language' => 'es',
        ], as: $this->admin()), 201);

        self::assertSame('Trimmed', $video['title']);
        self::assertSame('', $video['description']);
        self::assertSame('', $video['category']);
        self::assertSame(0, $video['order']);
        self::assertSame(0, $video['duration_minutes']);
    }

    public function testABadIdIsInvalidUuid(): void
    {
        $this->assertApiError($this->api('GET', self::ADMIN_URL.'/nope', as: $this->admin()), 400, 'INVALID_UUID');
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function body(array $override): array
    {
        return array_merge([
            'title' => 'Getting started',
            'description' => 'Step by step.',
            'url' => 'https://www.youtube.com/watch?v=abcDEF12345',
            'language' => 'en',
            'category' => 'getting-started',
            'order' => 1,
            'duration_minutes' => 4,
        ], $override);
    }

    /** @param array<string, mixed> $override */
    private function create(string $admin, array $override): string
    {
        return $this->data($this->api('POST', self::ADMIN_URL, $this->body($override), as: $admin), 201)['id'];
    }
}
