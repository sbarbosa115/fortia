<?php

namespace App\Responses\Application;

use App\Identity\Application\Query\AccountQueries;
use App\Responses\Domain\Error\QuestionNotFound;
use App\Responses\Domain\Error\SessionNotFound;
use App\Responses\Domain\Repository\SessionRepository;
use App\Shared\Application\Storage\ObjectStorage;
use App\Shared\Application\Storage\SignedUpload;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Ids;

/**
 * Signed URLs for files (PRD §8.4 "Files and transcription", §13.5):
 *
 * - an answer's file: a form-style upload of 1 byte to 500 MB to {customer_id}/{session_id}/{question_id}/{md5}{ext},
 *   only for a session still being filled that belongs to that account and has that question (D4: not for any
 *   customer_id a caller names);
 * - a chain prompt's text: a direct upload to prompts/{customer_id}/{uuid}{ext|.txt};
 * - a download of an answer's file, inline or as an attachment.
 *
 * Each URL lasts 15 minutes.
 */
final class AnswerFiles
{
    public const MAX_BYTES = 500 * 1024 * 1024;

    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly AccountQueries $accounts,
        private readonly ObjectStorage $storage,
    ) {
    }

    public function answerUpload(string $customerId, string $sessionId, string $questionId, string $filename, string $contentType): SignedUpload
    {
        $session = $this->sessions->find($sessionId);
        if (null === $session || $session->customerId() !== $customerId || $session->isEnded()) {
            throw new SessionNotFound();
        }
        $questionIds = array_map(static fn (array $q): string => (string) ($q['id'] ?? ''), $session->questions());
        if (!\in_array($questionId, $questionIds, true)) {
            throw new QuestionNotFound();
        }
        $md5 = md5($sessionId.'|'.$questionId.'|'.$filename.'|'.Ids::uuid4());
        $key = \sprintf('%s/%s/%s/%s%s', $customerId, $sessionId, $questionId, $md5, self::extension($filename));

        return $this->storage->signedUploadForm($key, $contentType, 1, self::MAX_BYTES);
    }

    public function promptUpload(string $customerId, string $filename, string $contentType): SignedUpload
    {
        if (!$this->accounts->exists($customerId)) {
            throw new NotFound('CUSTOMER_NOT_FOUND', 'The account does not exist.');
        }
        $key = \sprintf('prompts/%s/%s%s', $customerId, Ids::uuid4(), self::extension($filename) ?: '.txt');

        return $this->storage->signedPutUrl($key, $contentType);
    }

    /** @param 'inline'|'attachment' $disposition */
    public function download(string $key, string $disposition): string
    {
        return $this->storage->signedDownloadUrl($key, $disposition);
    }

    /**
     * An answer file's key: exactly 4 non-empty segments, no "..", at most 1024 characters (PRD §8.4).
     */
    public static function isAnswerFileKey(string $key): bool
    {
        if ('' === $key || \strlen($key) > 1024 || str_contains($key, '..')) {
            return false;
        }
        $segments = explode('/', $key);

        return 4 === \count($segments) && [] === array_filter($segments, static fn (string $s): bool => '' === trim($s));
    }

    /** ".pdf" from "Report.PDF"; empty when there is none or it is not a plain extension. */
    private static function extension(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, \PATHINFO_EXTENSION));

        return 1 === preg_match('/^[a-z0-9]{1,10}$/', $extension) ? '.'.$extension : '';
    }
}
