<?php

namespace App\Content\UI\Http\Controller;

use App\Content\Application\Query\VideoQueries;
use App\Content\UI\Http\Output\VideoListOutput;
use App\Shared\Application\Security\Caller;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** PRD §8.12: the documentation videos the console's /documentation page embeds. Any signed-in user. */
#[OA\Tag(name: 'Documentation')]
final class VideosController
{
    public function __construct(private readonly VideoQueries $videos)
    {
    }

    /** Sorted by order and then by title. Without ?language, both languages. */
    #[Route('/videos', name: 'api_videos_list', methods: ['GET'])]
    #[OA\Parameter(name: 'language', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['es', 'en']))]
    #[OA\Response(response: 200, description: '{videos}', content: new Model(type: VideoListOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_LANGUAGE')]
    #[OA\Response(response: 401, description: 'UNAUTHORIZED')]
    public function list(Caller $caller, Request $request): JsonResponse
    {
        $language = $request->query->has('language') ? $request->query->getString('language') : null;

        return ApiResponse::ok(VideoListOutput::of($this->videos->list($language)));
    }
}
