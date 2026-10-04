<?php

namespace App\Responses\UI\Http\Controller;

use App\Responses\Application\AnswerFiles;
use App\Responses\Application\Port\TranscriptionTokens;
use App\Responses\UI\Http\Input\DownloadUrlInput;
use App\Responses\UI\Http\Input\SignedUrlInput;
use App\Responses\UI\Http\Input\TemplateDownloadInput;
use App\Responses\UI\Http\Output\DownloadUrlOutput;
use App\Responses\UI\Http\Output\SignedUploadOutput;
use App\Responses\UI\Http\Output\TranscriptionTokenOutput;
use App\Responses\UI\Http\PublicRateLimit;
use App\Shared\Application\Security\Caller;
use App\Shared\Application\Storage\ObjectStorage;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\Domain\Error\Unauthenticated;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** PRD §8.4 "Files and transcription". */
#[OA\Tag(name: 'Files and transcription')]
final class FilesController
{
    public function __construct(
        private readonly AnswerFiles $files,
        private readonly TranscriptionTokens $transcription,
        private readonly PublicRateLimit $rateLimit,
    ) {
    }

    /**
     * An ephemeral secret for one recording (~1 min, §13.4). P, rate limited. customer_id: the account of the session,
     * whose own OpenAI key is used when it saved one.
     */
    #[Route('/transcription/token', name: 'api_transcription_token', methods: ['GET'])]
    #[OA\Parameter(name: 'customer_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', pattern: '^[A-Za-z0-9]{1,16}$'))]
    #[OA\Response(response: 200, description: 'The token', content: new Model(type: TranscriptionTokenOutput::class))]
    #[OA\Response(response: 502, description: 'INTERNAL_ERROR (the provider did not issue one)')]
    public function transcriptionToken(Request $request): JsonResponse
    {
        $this->rateLimit->consume($request);
        $customerId = $request->query->getString('customer_id');
        $token = $this->transcription->issue(1 === preg_match('/^[A-Za-z0-9]{1,16}$/', $customerId) ? $customerId : null);

        return ApiResponse::ok(new TranscriptionTokenOutput($token->token, $token->provider, $token->expiresIn));
    }

    /**
     * A signed upload. answer_media (P): only for a session still being filled, of that account, with that question
     * (D4). prompt and template: the account's own users only (written in the console).
     */
    #[Route('/signed-urls', name: 'api_signed_urls', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The signed upload', content: new Model(type: SignedUploadOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 401, description: 'UNAUTHORIZED (a prompt upload without a console user)')]
    #[OA\Response(response: 403, description: 'FORBIDDEN (a prompt upload for another account)')]
    #[OA\Response(response: 404, description: 'SESSION_NOT_FOUND, QUESTION_NOT_FOUND, CUSTOMER_NOT_FOUND')]
    public function signedUrl(#[Payload(allowExtraFields: false)] SignedUrlInput $input, Request $request, ?Caller $caller): JsonResponse
    {
        $this->rateLimit->consume($request);
        $customerId = (string) $input->customer_id;
        if (SignedUrlInput::PROMPT === $input->uploadType()) {
            if (null === $caller) {
                throw new Unauthenticated('UNAUTHORIZED', 'Sign in to upload a prompt.');
            }
            if (!$caller->owns($customerId)) {
                throw new NotAllowed('FORBIDDEN', 'You can only upload prompts to your own account.');
            }

            return ApiResponse::ok(SignedUploadOutput::put($this->files->promptUpload($customerId, (string) $input->filename, (string) $input->content_type)));
        }
        if (SignedUrlInput::TEMPLATE === $input->uploadType()) {
            if (null === $caller) {
                throw new Unauthenticated('UNAUTHORIZED', 'Sign in to upload a template.');
            }
            if (!$caller->owns($customerId)) {
                throw new NotAllowed('FORBIDDEN', 'You can only upload templates to your own account.');
            }

            return ApiResponse::ok(SignedUploadOutput::form($this->files->templateUpload($customerId, (string) $input->filename, (string) $input->content_type)));
        }

        return ApiResponse::ok(SignedUploadOutput::form($this->files->answerUpload(
            $customerId,
            strtolower((string) $input->session_id),
            (string) $input->question_id,
            (string) $input->filename,
            (string) $input->content_type,
        )));
    }

    /**
     * A signed download of a file question's template (P, rate limited): the respondent downloads it, fills it in and
     * uploads it as the answer. Only keys of templates (templates/{customer_id}/{uuid}/{filename}).
     */
    #[Route('/templates/download-urls', name: 'api_templates_download', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The signed URL', content: new Model(type: DownloadUrlOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 404, description: 'TEMPLATE_NOT_FOUND')]
    public function templateDownloadUrl(#[Payload(allowExtraFields: false)] TemplateDownloadInput $input, Request $request): JsonResponse
    {
        $this->rateLimit->consume($request);

        return ApiResponse::ok(new DownloadUrlOutput($this->files->templateDownload((string) $input->key), ObjectStorage::SIGNED_URL_TTL));
    }

    /** A signed download of an answer's file, for the account it belongs to (the key's first segment). */
    #[Route('/answers-media/download-urls', name: 'api_answers_media_download', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The signed URL', content: new Model(type: DownloadUrlOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 401, description: 'UNAUTHORIZED')]
    #[OA\Response(response: 403, description: 'FORBIDDEN (another account\'s file)')]
    public function downloadUrl(#[Payload(allowExtraFields: false)] DownloadUrlInput $input, Caller $caller): JsonResponse
    {
        $key = (string) $input->key;
        if (!$caller->owns(explode('/', $key)[0])) {
            throw new NotAllowed('FORBIDDEN', 'This file belongs to another account.');
        }

        return ApiResponse::ok(new DownloadUrlOutput($this->files->download($key, $input->disposition()), ObjectStorage::SIGNED_URL_TTL));
    }
}
