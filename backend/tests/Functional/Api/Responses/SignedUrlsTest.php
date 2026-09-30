<?php

namespace App\Tests\Functional\Api\Responses;

use App\Shared\Domain\Ids;
use App\Tests\Support\ApiTestCase;

/** Files and transcription (PRD §8.4, §13.4, §13.5, D4). */
final class SignedUrlsTest extends ApiTestCase
{
    use SessionFixtures;

    public function testAnAnswerFileUploadIsSignedForASessionBeingFilled(): void
    {
        $this->account('ACME0001');
        $session = $this->startSession($this->questionnaire('ACME0001'));

        $upload = $this->data($this->api('POST', '/api/v1/signed-urls', [
            'filename' => 'Report.PDF', 'content_type' => 'application/pdf', 'customer_id' => 'ACME0001',
            'session_id' => $session['session_id'], 'question_id' => 'q1',
        ]));

        self::assertMatchesRegularExpression('#^ACME0001/'.$session['session_id'].'/q1/[0-9a-f]{32}\.pdf$#', $upload['key'], '§8.4: key {customer_id}/{session_id}/{question_id}/{md5}{ext}');
        self::assertSame(900, $upload['expires_in']);
        self::assertSame($upload['key'], $upload['fields']['key']);
        self::assertSame('1', $upload['fields']['min']);
        self::assertSame((string) (500 * 1024 * 1024), $upload['fields']['max'], '1 byte to 500 MB');
        self::assertStringEndsWith('/storage/upload', $upload['url']);
    }

    public function testAnAnswerFileNeedsAValidSessionOfThatAccount(): void
    {
        $this->account('ACME0001');
        $this->account('GLOBEX01');
        $session = $this->startSession($this->questionnaire('ACME0001'));
        $body = ['filename' => 'a.png', 'content_type' => 'image/png', 'customer_id' => 'ACME0001', 'session_id' => $session['session_id'], 'question_id' => 'q1'];

        $this->assertApiError($this->api('POST', '/api/v1/signed-urls', ['customer_id' => 'GLOBEX01'] + $body), 404, 'SESSION_NOT_FOUND', 'D4: not for any customer_id a caller names');
        $this->assertApiError($this->api('POST', '/api/v1/signed-urls', ['session_id' => Ids::uuid4()] + $body), 404, 'SESSION_NOT_FOUND');
        $this->assertApiError($this->api('POST', '/api/v1/signed-urls', ['question_id' => 'nope'] + $body), 404, 'QUESTION_NOT_FOUND');
        $this->assertApiError($this->api('POST', '/api/v1/signed-urls', array_diff_key($body, ['session_id' => 1])), 400, 'VALIDATION_ERROR', 'session_id is required for answer_media');
        $this->assertApiError($this->api('POST', '/api/v1/signed-urls', ['content_type' => 'pdf'] + $body), 400, 'VALIDATION_ERROR', 'content_type is type/subtype');

        $this->data($this->api('POST', '/api/v1/questionnaire/session', self::answered($session, ['q1' => 'done'])));
        $this->assertApiError($this->api('POST', '/api/v1/signed-urls', $body), 404, 'SESSION_NOT_FOUND', 'D4: a submitted session takes no more files');
    }

    public function testAPromptUploadIsForTheAccountsOwnUsers(): void
    {
        $this->account('ACME0001');
        $this->account('GLOBEX01');
        $body = ['filename' => 'instructions', 'content_type' => 'text/plain', 'customer_id' => 'ACME0001', 'upload_type' => 'prompt'];

        $upload = $this->data($this->api('POST', '/api/v1/signed-urls', $body, as: 'root@acme0001.test'));

        self::assertMatchesRegularExpression('#^prompts/ACME0001/[0-9a-f-]{36}\.txt$#', $upload['key'], '§8.4: prompts/{customer_id}/{uuid}{ext|.txt}');
        self::assertNull($upload['fields']);
        self::assertStringContainsString('/storage/put?', $upload['url']);
        $this->assertApiError($this->api('POST', '/api/v1/signed-urls', $body), 401, 'UNAUTHORIZED');
        $this->assertApiError($this->api('POST', '/api/v1/signed-urls', $body, as: 'root@globex01.test'), 403, 'FORBIDDEN');
    }

    public function testAnAnswerFileIsDownloadedOnlyByItsAccount(): void
    {
        $this->account('ACME0001');
        $this->account('GLOBEX01');
        $key = 'ACME0001/'.Ids::uuid4().'/q1/abc.pdf';

        $download = $this->data($this->api('POST', '/api/v1/answers-media/download-urls', ['key' => $key, 'disposition' => 'inline'], as: 'root@acme0001.test'));

        self::assertStringContainsString('/storage/download?', $download['url']);
        self::assertStringContainsString('disposition=inline', $download['url']);
        self::assertSame(900, $download['expires_in']);
        $this->assertApiError($this->api('POST', '/api/v1/answers-media/download-urls', ['key' => $key], as: 'root@globex01.test'), 403, 'FORBIDDEN', '§8.4: 403 if the first segment is not the caller\'s customer_id');
        self::assertSame(200, $this->api('POST', '/api/v1/answers-media/download-urls', ['key' => $key], as: $this->admin())['status'], '…except Admin');
        $this->assertApiError($this->api('POST', '/api/v1/answers-media/download-urls', ['key' => $key]), 401, 'UNAUTHORIZED');
        foreach (['ACME0001/a/b', 'ACME0001/a/../c/d', 'ACME0001//b/c', 'ACME0001/a/b/c/d'] as $bad) {
            $this->assertApiError($this->api('POST', '/api/v1/answers-media/download-urls', ['key' => $bad], as: 'root@acme0001.test'), 400, 'VALIDATION_ERROR', "bad key $bad");
        }
    }

    public function testATranscriptionTokenIsIssuedPerRecording(): void
    {
        $first = $this->data($this->api('GET', '/api/v1/transcription/token'));
        $second = $this->data($this->api('GET', '/api/v1/transcription/token'));

        self::assertSame('browser', $first['provider'], 'TRANSCRIPTION_PROVIDER=browser in tests');
        self::assertNotSame($first['token'], $second['token'], '§8.4: one per recording');
        self::assertSame(60, $first['expires_in']);
    }
}
