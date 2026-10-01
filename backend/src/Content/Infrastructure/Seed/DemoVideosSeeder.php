<?php

namespace App\Content\Infrastructure\Seed;

use App\Content\Domain\Model\Video;
use App\Content\Domain\Repository\VideoRepository;
use App\Shared\Application\Seed\DemoSeeder;
use App\Shared\Domain\Clock;

/**
 * Demo documentation videos for the regression suite (docs/tests/ui-regression.md, DOC cases): three in Spanish and
 * two in English, with fixed ids. The YouTube ids are placeholders (the player says the video is unavailable): the
 * platform Admin replaces them through /admin/videos.
 */
final class DemoVideosSeeder implements DemoSeeder
{
    private const VIDEOS = [
        ['7d1c2b10-0c5e-4a8f-9d20-000000000001', 'es', 1, 'Primeros pasos en Mappi', 'Un recorrido por la consola: cuestionarios, respuestas y analítica.', 'getting-started', 4, 'mappiDemo01'],
        ['7d1c2b10-0c5e-4a8f-9d20-000000000002', 'es', 2, 'Crea un cuestionario con IA', 'Describe lo que necesitas y deja que Mappi proponga las preguntas.', 'questionnaires', 6, 'mappiDemo02'],
        ['7d1c2b10-0c5e-4a8f-9d20-000000000003', 'es', 3, 'Lee el tablero de un cuestionario', 'Embudo, abandono por pregunta y distribución de respuestas.', 'analytics', 5, 'mappiDemo03'],
        ['7d1c2b10-0c5e-4a8f-9d20-000000000004', 'en', 1, 'Getting started with Mappi', 'A tour of the console: questionnaires, answers and analytics.', 'getting-started', 4, 'mappiDemo04'],
        ['7d1c2b10-0c5e-4a8f-9d20-000000000005', 'en', 2, 'Create a questionnaire with AI', 'Describe what you need and let Mappi draft the questions.', 'questionnaires', 6, 'mappiDemo05'],
    ];

    public function __construct(
        private readonly VideoRepository $videos,
        private readonly Clock $clock,
    ) {
    }

    public static function priority(): int
    {
        return 40;
    }

    public function seed(): void
    {
        foreach (self::VIDEOS as [$id, $language, $order, $title, $description, $category, $minutes, $youtubeId]) {
            if (null !== $this->videos->find($id)) {
                continue;
            }
            $this->videos->add(new Video($id, $title, $description, 'https://www.youtube.com/watch?v='.$youtubeId, $language, $category, $order, $minutes, $this->clock->now()));
        }
    }
}
