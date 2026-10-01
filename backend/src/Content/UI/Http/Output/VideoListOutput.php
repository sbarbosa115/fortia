<?php

namespace App\Content\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** GET /videos and GET /admin/videos: {videos}, by order and then title (PRD §8.12). */
final class VideoListOutput
{
    /**
     * @param list<VideoOutput> $videos
     */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: VideoOutput::class)))]
        public readonly array $videos,
    ) {
    }

    /** @param list<array<string, mixed>> $rows */
    public static function of(array $rows): self
    {
        return new self(array_map(VideoOutput::of(...), $rows));
    }
}
