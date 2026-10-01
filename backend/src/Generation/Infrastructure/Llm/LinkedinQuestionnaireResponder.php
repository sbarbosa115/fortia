<?php

namespace App\Generation\Infrastructure\Llm;

use App\Generation\Application\Job\LinkedinQuestionnaireJob;
use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmResponse;
use App\Shared\Infrastructure\Llm\Fake\FakeLlmResponder;

/**
 * Offline LinkedIn diagnostic (PRD §7.18): eight radio questions in four categories (values 0–3) and three tiers,
 * in the requested language, titled with the profile's name.
 */
final class LinkedinQuestionnaireResponder implements FakeLlmResponder
{
    private const TEXTS = [
        'en' => [
            'title' => 'Leadership diagnostic for %s',
            'description' => 'How mature are the practices of your area?',
            'choices' => ['Not at all', 'A little', 'Mostly', 'Fully'],
            'questions' => [
                'Strategy' => ['Are your area\'s goals written down and shared?', 'Do you review them every quarter?'],
                'People' => ['Does every role have clear responsibilities?', 'Do people get regular feedback?'],
                'Process' => ['Are your key processes documented?', 'Do you measure how they perform?'],
                'Technology' => ['Do your tools fit the way you work?', 'Is your data reliable enough to decide with?'],
            ],
            'tiers' => [
                ['Foundations', 'The basics are not in place yet.', 'Write down three goals for the quarter.', 'Agree on the goals with your team.'],
                ['Developing', 'Good practices exist in some areas.', 'Measure your two most important processes.', 'Pick one metric per process.'],
                ['Advanced', 'Your area runs on clear goals, people and data.', 'Share your practices with other areas.', 'Run a quarterly review with peers.'],
            ],
        ],
        'es' => [
            'title' => 'Diagnóstico de liderazgo para %s',
            'description' => '¿Qué tan maduras son las prácticas de tu área?',
            'choices' => ['Nada', 'Un poco', 'En gran parte', 'Totalmente'],
            'questions' => [
                'Estrategia' => ['¿Los objetivos de tu área están escritos y compartidos?', '¿Los revisas cada trimestre?'],
                'Personas' => ['¿Cada rol tiene responsabilidades claras?', '¿Las personas reciben retroalimentación periódica?'],
                'Procesos' => ['¿Tus procesos clave están documentados?', '¿Mides cómo se desempeñan?'],
                'Tecnología' => ['¿Tus herramientas se ajustan a tu forma de trabajar?', '¿Tus datos son confiables para decidir?'],
            ],
            'tiers' => [
                ['Bases', 'Lo básico aún no está en marcha.', 'Escribe tres objetivos para el trimestre.', 'Acuerda los objetivos con tu equipo.'],
                ['En desarrollo', 'Hay buenas prácticas en algunas áreas.', 'Mide tus dos procesos más importantes.', 'Elige una métrica por proceso.'],
                ['Avanzado', 'Tu área funciona con objetivos, personas y datos claros.', 'Comparte tus prácticas con otras áreas.', 'Haz una revisión trimestral con tus pares.'],
            ],
        ],
    ];

    public function supports(LlmRequest $request): bool
    {
        return LinkedinQuestionnaireJob::PURPOSE === $request->purpose;
    }

    public function respond(LlmRequest $request): LlmResponse
    {
        $texts = self::TEXTS['en' === ($request->context['language'] ?? null) ? 'en' : 'es'];
        $name = (string) ($request->context['profile']['name'] ?? '');

        $questions = [];
        foreach ($texts['questions'] as $category => $titles) {
            foreach ($titles as $title) {
                $questions[] = [
                    'title' => $title,
                    'description' => '',
                    'category' => $category,
                    'type' => 'radio',
                    'choices' => array_map(static fn (string $label, int $value): array => ['label' => $label, 'value' => $value], $texts['choices'], array_keys($texts['choices'])),
                ];
            }
        }

        return LlmResponse::json([
            'title' => \sprintf($texts['title'], $name),
            'description' => $texts['description'],
            'questions' => $questions,
            'tiers' => array_map(static fn (array $t): array => ['name' => $t[0], 'description' => $t[1], 'recommendations' => [$t[2]], 'action_plan' => [$t[3]]], $texts['tiers']),
        ]);
    }
}
