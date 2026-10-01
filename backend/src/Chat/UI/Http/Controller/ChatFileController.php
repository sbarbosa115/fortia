<?php

namespace App\Chat\UI\Http\Controller;

use App\Chat\Application\Document\AttachedFiles;
use App\Chat\UI\Http\Output\ChatFileOutput;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\Domain\Error\Rejected;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A document attached to the chat (a Word file, a PDF, Markdown…) to build a questionnaire from it: AG. Multipart with one "file"; the answer is its text, which the client sends back with its message on every
 * turn (nothing is stored).
 */
#[OA\Tag(name: 'Chat')]
final class ChatFileController
{
    public function __construct(
        private readonly AttachedFiles $files,
    ) {
    }

    #[Route('/chat/files', name: 'api_chat_files', methods: ['POST'])]
    #[OA\RequestBody(content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(
        required: ['file'],
        properties: [new OA\Property(property: 'file', description: 'docx, pdf, md, markdown, txt or csv; up to 10 MB', type: 'string', format: 'binary')],
    )))]
    #[OA\Response(response: 200, description: 'The text read from the file', content: new Model(type: ChatFileOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR (no file)')]
    #[OA\Response(response: 401, description: 'UNAUTHORIZED')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 422, description: 'UNSUPPORTED_FILE_TYPE, FILE_TOO_LARGE, FILE_TOO_LONG, FILE_HAS_NO_TEXT, FILE_UNREADABLE')]
    public function __invoke(Caller $caller, Request $request): JsonResponse
    {
        if (!$caller->inAdminGroups()) {
            throw new NotAllowed('FORBIDDEN', 'Admin privileges are required.');
        }
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new Rejected('VALIDATION_ERROR', 'Attach one file in the "file" field.', ['file' => $file instanceof UploadedFile ? $file->getErrorMessage() : 'This value should not be blank.']);
        }

        $read = $this->files->read($file->getClientOriginalName(), (string) file_get_contents($file->getPathname()));

        return ApiResponse::ok(new ChatFileOutput($read->filename, $read->text));
    }
}
