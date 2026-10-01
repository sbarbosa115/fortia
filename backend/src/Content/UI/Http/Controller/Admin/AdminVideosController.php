<?php

namespace App\Content\UI\Http\Controller\Admin;

use App\Content\Application\Command\DeleteVideo;
use App\Content\Application\Command\SaveVideo;
use App\Content\Application\Query\VideoQueries;
use App\Content\Domain\Error\VideoNotFound;
use App\Content\UI\Http\Input\VideoInput;
use App\Content\UI\Http\Output\VideoListOutput;
use App\Content\UI\Http\Output\VideoOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Request\RouteId;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.13 videos: CRUD of the documentation videos (fields of §6.22). Admin only: a Customer-Admin or a read-only
 * user gets 403 FORBIDDEN. /admin/* ignores X-Assume-Customer-Id, so an Admin assuming an account still passes.
 */
#[OA\Tag(name: 'Admin')]
final class AdminVideosController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly VideoQueries $videos,
    ) {
    }

    /** Every video (or one language), by order and then title. */
    #[Route('/admin/videos', name: 'api_admin_videos_list', methods: ['GET'])]
    #[OA\Parameter(name: 'language', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['es', 'en']))]
    #[OA\Response(response: 200, description: '{videos}', content: new Model(type: VideoListOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_LANGUAGE')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    public function list(Caller $caller, Request $request): JsonResponse
    {
        self::assertAdmin($caller);
        $language = $request->query->has('language') ? $request->query->getString('language') : null;

        return ApiResponse::ok(VideoListOutput::of($this->videos->list($language)));
    }

    #[Route('/admin/videos/{id}', name: 'api_admin_videos_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The video', content: new Model(type: VideoOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_UUID')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'VIDEO_NOT_FOUND')]
    public function get(Caller $caller, string $id): JsonResponse
    {
        $id = RouteId::uuid($id);
        self::assertAdmin($caller);

        return ApiResponse::ok($this->present($id));
    }

    #[Route('/admin/videos', name: 'api_admin_videos_create', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: VideoInput::class))]
    #[OA\Response(response: 201, description: 'The video', content: new Model(type: VideoOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    public function create(Caller $caller, #[Payload(allowExtraFields: false)] VideoInput $input): JsonResponse
    {
        self::assertAdmin($caller);
        $id = (string) $this->commands->dispatch(self::command(null, $input));

        return ApiResponse::created($this->present($id));
    }

    /** Full replacement: the same body as POST. */
    #[Route('/admin/videos/{id}', name: 'api_admin_videos_update', methods: ['PUT'])]
    #[OA\RequestBody(content: new Model(type: VideoInput::class))]
    #[OA\Response(response: 200, description: 'The video', content: new Model(type: VideoOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR, INVALID_UUID')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'VIDEO_NOT_FOUND')]
    public function update(Caller $caller, string $id, #[Payload(allowExtraFields: false)] VideoInput $input): JsonResponse
    {
        $id = RouteId::uuid($id);
        self::assertAdmin($caller);
        $this->commands->dispatch(self::command($id, $input));

        return ApiResponse::ok($this->present($id));
    }

    #[Route('/admin/videos/{id}', name: 'api_admin_videos_delete', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Deleted')]
    #[OA\Response(response: 400, description: 'INVALID_UUID')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'VIDEO_NOT_FOUND')]
    public function delete(Caller $caller, string $id): Response
    {
        $id = RouteId::uuid($id);
        self::assertAdmin($caller);
        $this->commands->dispatch(new DeleteVideo($id));

        return ApiResponse::noContent();
    }

    private function present(string $id): VideoOutput
    {
        return VideoOutput::of($this->videos->find($id) ?? throw new VideoNotFound());
    }

    private static function command(?string $id, VideoInput $input): SaveVideo
    {
        return new SaveVideo($id, $input->title(), $input->description(), $input->url(), $input->language(), $input->category(), $input->order(), $input->durationMinutes());
    }

    private static function assertAdmin(Caller $caller): void
    {
        if (!$caller->isAdmin()) {
            throw new NotAllowed('FORBIDDEN', 'Super-admin privileges are required.');
        }
    }
}
