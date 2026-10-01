<?php

namespace App\Content\Application\Command;

use App\Content\Domain\Error\VideoNotFound;
use App\Content\Domain\Model\Video;
use App\Content\Domain\Repository\VideoRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class SaveVideoHandler
{
    public function __construct(
        private readonly VideoRepository $videos,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SaveVideo $command): string
    {
        $now = $this->clock->now();
        if (null === $command->videoId) {
            $video = new Video(Ids::uuid4(), $command->title, $command->description, $command->url, $command->language, $command->category, $command->order, $command->durationMinutes, $now);
            $this->videos->add($video);

            return $video->id();
        }

        $video = $this->videos->find($command->videoId) ?? throw new VideoNotFound();
        $video->change($command->title, $command->description, $command->url, $command->language, $command->category, $command->order, $command->durationMinutes, $now);

        return $video->id();
    }
}
