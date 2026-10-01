<?php

namespace App\Chat\Application\Tool;

use App\Content\Application\Query\VideoQueries;
use App\Shared\Application\Security\Caller;

/** PRD §7.19 "Documentation" (list_videos). */
final class DocumentationTools implements ChatToolbox
{
    public function __construct(private readonly VideoQueries $videos)
    {
    }

    public function tools(): array
    {
        return [
            ChatTool::read(
                'list_videos',
                'The documentation videos, in order.',
                ['language' => Schema::enum(['es', 'en'], 'Only the videos in this language.')],
                [],
                fn (Caller $caller, ToolInput $input): array => ['rows' => array_map(
                    static fn (array $v): array => array_intersect_key($v, array_flip(['id', 'title', 'description', 'url', 'language', 'order'])),
                    $this->videos->list(null === ($input->all()['language'] ?? null) ? null : $input->choice('language', ['es', 'en'])),
                )],
            ),
        ];
    }
}
