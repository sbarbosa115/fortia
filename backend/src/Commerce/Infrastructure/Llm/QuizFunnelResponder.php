<?php

namespace App\Commerce\Infrastructure\Llm;

use App\Commerce\Domain\QuizFunnel;
use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmResponse;
use App\Shared\Infrastructure\Llm\Fake\FakeLlmResponder;

/**
 * Offline quiz funnel generation (PRD §7.17 step 3), predictable from the request: a profiling quiz or a product
 * experience of five single- and multiple-choice questions, in the account's language, titled after the store.
 */
final class QuizFunnelResponder implements FakeLlmResponder
{
    private const QUESTIONS = [
        'es' => [
            QuizFunnel::EXPERIENCE => [
                ['¿Qué estás buscando hoy?', 'radio', ['Algo para mí', 'Un regalo', 'Reponer un favorito']],
                ['¿Qué es lo más importante para ti?', 'radio', ['Calidad', 'Precio', 'Que sea fácil de usar', 'Que sea sostenible']],
                ['¿Con qué frecuencia lo usarás?', 'radio', ['Todos los días', 'Algunas veces por semana', 'De vez en cuando']],
                ['¿Qué estilo prefieres?', 'checkbox', ['Clásico', 'Intenso', 'Suave', 'Novedoso']],
                ['¿Cuál es tu presupuesto?', 'select', ['Menos de 20 USD', 'Entre 20 y 50 USD', 'Más de 50 USD']],
            ],
            QuizFunnel::PROFILING => [
                ['¿Cómo te describirías?', 'radio', ['Principiante', 'Aficionado', 'Experto']],
                ['¿Qué te trae a nuestra tienda?', 'radio', ['Descubrir algo nuevo', 'Resolver una necesidad', 'Buscar un regalo']],
                ['¿Qué valoras al comprar?', 'checkbox', ['Calidad', 'Precio', 'Marca', 'Envío rápido']],
                ['¿Cuándo lo vas a usar?', 'radio', ['En casa', 'En el trabajo', 'De viaje']],
                ['¿Cuál es tu presupuesto?', 'select', ['Menos de 20 USD', 'Entre 20 y 50 USD', 'Más de 50 USD']],
            ],
        ],
        'en' => [
            QuizFunnel::EXPERIENCE => [
                ['What are you looking for today?', 'radio', ['Something for me', 'A gift', 'Restocking a favourite']],
                ['What matters most to you?', 'radio', ['Quality', 'Price', 'Ease of use', 'Sustainability']],
                ['How often will you use it?', 'radio', ['Every day', 'A few times a week', 'Now and then']],
                ['Which style do you prefer?', 'checkbox', ['Classic', 'Bold', 'Gentle', 'Something new']],
                ['What is your budget?', 'select', ['Under 20 USD', '20 to 50 USD', 'Over 50 USD']],
            ],
            QuizFunnel::PROFILING => [
                ['How would you describe yourself?', 'radio', ['Beginner', 'Enthusiast', 'Expert']],
                ['What brings you to our store?', 'radio', ['Discovering something new', 'Solving a need', 'Finding a gift']],
                ['What do you value when you buy?', 'checkbox', ['Quality', 'Price', 'Brand', 'Fast shipping']],
                ['Where will you use it?', 'radio', ['At home', 'At work', 'On the go']],
                ['What is your budget?', 'select', ['Under 20 USD', '20 to 50 USD', 'Over 50 USD']],
            ],
        ],
    ];

    public function supports(LlmRequest $request): bool
    {
        return \in_array($request->purpose, [QuizFunnel::PURPOSE_EXPERIENCE, QuizFunnel::PURPOSE_PROFILING], true);
    }

    public function respond(LlmRequest $request): LlmResponse
    {
        $language = 'en' === ($request->context['language'] ?? null) ? 'en' : 'es';
        $variant = QuizFunnel::PROFILING === ($request->context['variant'] ?? null) ? QuizFunnel::PROFILING : QuizFunnel::EXPERIENCE;
        $host = (string) (parse_url((string) ($request->context['store'] ?? ''), \PHP_URL_HOST) ?: 'la tienda');
        $store = ucfirst(explode('.', $host)[0]);

        $questions = array_map(static fn (array $q): array => [
            'title' => $q[0],
            'description' => '',
            'type' => $q[1],
            'choices' => $q[2],
        ], self::QUESTIONS[$language][$variant]);

        return LlmResponse::json([
            'title' => 'en' === $language
                ? (QuizFunnel::PROFILING === $variant ? "Get to know your style at $store" : "Find your perfect match at $store")
                : (QuizFunnel::PROFILING === $variant ? "Descubre tu estilo en $store" : "Encuentra tu producto ideal en $store"),
            'description' => 'en' === $language
                ? 'Answer a few quick questions and we will recommend the right products for you.'
                : 'Responde unas preguntas rápidas y te recomendaremos los productos ideales para ti.',
            'questions' => $questions,
        ]);
    }
}
