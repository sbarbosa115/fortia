<?php

namespace App\Content\Application\Command;

use App\Content\Domain\Error\VideoNotFound;
use App\Content\Domain\Repository\VideoRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeleteVideoHandler
{
    public function __construct(private readonly VideoRepository $videos)
    {
    }

    public function __invoke(DeleteVideo $command): void
    {
        $video = $this->videos->find($command->videoId) ?? throw new VideoNotFound();
        $this->videos->remove($video);
    }
}
