<?php

namespace App\Chat\Infrastructure\Llm;

use App\Chat\Application\ChatTurn;
use App\Shared\Application\Llm\LlmMessage;
use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmResponse;
use App\Shared\Application\Llm\LlmToolCall;
use App\Shared\Domain\Text;
use App\Shared\Infrastructure\Llm\Fake\FakeLlmResponder;

/**
 * The offline assistant (PRD §7.19) for dev and tests: a keyword script, in Spanish or English, that calls the same
 * tools a real model would, so every path of a turn (reads, queued writes, the draft through its phases) runs
 * without a provider.
 *
 * - "crea un cuestionario sobre X" / "create a questionnaire about X" (+ "diagnóstico"/"diagnostic", "cadena"/"chain")
 *   → update_draft with the basics; then "sí" → confirm_basics, three questions, the ending and request_review;
 * - with the questions under way, "agrega una tabla" / "add a table" and "agrega un archivo con plantilla" / "add a
 *   file with a template" → add_questions (a table, a file question with a CSV template) and request_review;
 * - "mis cuestionarios / organizaciones / asignaciones / proyectos / videos / usuarios / webhooks / claves",
 *   "mi plan", "planes" → the list tools;
 * - "crea la organización X" → create_organization (queued); "elimina/borra" + a selected organization →
 *   delete_organization; "desactiva" / "copia" / "edita" + a selected questionnaire → set_questionnaire_active,
 *   copy_questionnaire, load_questionnaire;
 * - "cambia el idioma a inglés" → update_account_language; "estilos de https://…" → extract_brand_styles;
 * - "detalles" / "details" with a selected item → its get_* tool.
 *
 * After the tools run it writes the answer from their results: a table for lists (with "see 5 more"), the queued
 * change to confirm, the draft's next step, or the error.
 */
final class ChatResponder implements FakeLlmResponder
{
    private const TEXTS = [
        'es' => [
            'greeting' => '¡Hola! Puedo crear cuestionarios contigo y ayudarte con tu cuenta: organizaciones, asignaciones, proyectos, tu plan y más. ¿Qué quieres hacer?',
            'replies' => ['Crear un cuestionario', 'Ver mis cuestionarios', 'Ver mi plan'],
            'basics' => "Estos son los datos básicos:\n\n- **Título:** %s\n- **Tipo:** %s\n- **Tema:** %s\n- **Página de inicio:** no\n- **Aviso legal:** no\n- **Captura de datos:** no\n\n¿Los confirmas?",
            'review' => "Este es el borrador completo de **%s** con %d preguntas:\n\n%s\n\n¿Lo creo?",
            'review_draft' => "Este es el borrador completo de **%s** con %d preguntas:\n\n%s\n\n¿Lo apruebas?",
            'queued' => 'Esto queda pendiente de tu confirmación: %s. ¿Lo hago?',
            'error' => 'No pude completar eso: %s',
            'empty' => 'No encontré nada.',
            'more' => 'Ver 5 más',
            'done' => 'Listo.',
            'yes' => 'Sí',
            'no' => 'No',
            'changes' => 'Hecho: %s.',
            'questions' => ['¿Qué tan satisfecho estás con %s?', '¿Qué es lo que más valoras de %s?', '¿Qué mejorarías de %s?'],
            'choices' => ['Nada', 'Poco', 'Bastante', 'Mucho'],
            'thanks' => '¡Gracias por responder!',
            'tiers' => [['Inicial', 'Hay mucho por mejorar.', 'Define tus objetivos.'], ['Avanzado', 'Vas por buen camino.', 'Comparte tus prácticas.']],
            'types' => ['regular' => 'regular', 'diagnostic' => 'diagnóstico', 'chain' => 'cadena'],
            'untitled' => 'Mi cuestionario',
            'chain_prompt' => 'Genera tres preguntas de seguimiento a partir de las respuestas.',
            'table' => ['¿Quiénes integran tu equipo?', ['Nombre', 'Cargo', 'Correo']],
            'file' => ['Sube tu presupuesto con la plantilla', 'plantilla-presupuesto.csv', ['Concepto', 'Cantidad', 'Costo'], ['Licencias', '10', '500']],
        ],
        'en' => [
            'greeting' => 'Hi! I can build questionnaires with you and help with your account: organizations, assignations, projects, your plan and more. What would you like to do?',
            'replies' => ['Create a questionnaire', 'See my questionnaires', 'See my plan'],
            'basics' => "These are the basics:\n\n- **Title:** %s\n- **Type:** %s\n- **Topic:** %s\n- **Landing page:** no\n- **Disclaimer:** no\n- **Data capture:** no\n\nDo you confirm them?",
            'review' => "Here is the complete draft of **%s** with %d questions:\n\n%s\n\nShall I create it?",
            'review_draft' => "Here is the complete draft of **%s** with %d questions:\n\n%s\n\nDo you approve it?",
            'queued' => 'This is waiting for your confirmation: %s. Shall I do it?',
            'error' => 'I couldn\'t do that: %s',
            'empty' => 'I found nothing.',
            'more' => 'See 5 more',
            'done' => 'Done.',
            'yes' => 'Yes',
            'no' => 'No',
            'changes' => 'Done: %s.',
            'questions' => ['How satisfied are you with %s?', 'What do you value most about %s?', 'What would you improve about %s?'],
            'choices' => ['Not at all', 'A little', 'Quite', 'Very'],
            'thanks' => 'Thanks for answering!',
            'tiers' => [['Starting', 'There is a lot to improve.', 'Set your goals.'], ['Advanced', 'You are on the right track.', 'Share your practices.']],
            'types' => ['regular' => 'regular', 'diagnostic' => 'diagnostic', 'chain' => 'chain'],
            'untitled' => 'My questionnaire',
            'chain_prompt' => 'Generate three follow-up questions from the answers.',
            'table' => ['Who is on your team?', ['Name', 'Role', 'Email']],
            'file' => ['Upload your budget using the template', 'budget-template.csv', ['Item', 'Quantity', 'Cost'], ['Licenses', '10', '500']],
        ],
    ];

    private const LISTS = [
        'list_questionnaires' => ['cuestionarios', 'questionnaires', 'encuestas', 'surveys'],
        'list_organizations' => ['organizaciones', 'organizations'],
        'list_assignations' => ['asignaciones', 'assignations'],
        'list_projects' => ['proyectos', 'projects'],
        'list_videos' => ['videos', 'tutoriales', 'tutorials'],
        'list_team_users' => ['usuarios', 'users', 'equipo', 'team'],
        'list_webhooks' => ['webhooks'],
        'list_api_keys' => ['claves', 'keys'],
        'list_plans' => ['planes', 'plans'],
        'get_plan_and_usage' => ['plan', 'uso', 'usage', 'consumo'],
    ];

    private int $calls = 0;

    public function supports(LlmRequest $request): bool
    {
        return ChatTurn::PURPOSE === $request->purpose;
    }

    public function respond(LlmRequest $request): LlmResponse
    {
        $language = 'en' === ($request->context['language'] ?? null) ? 'en' : 'es';
        $texts = self::TEXTS[$language];
        $last = $request->messages[\count($request->messages) - 1] ?? LlmMessage::user('');
        $draft = \is_array($request->context['draft'] ?? null) ? $request->context['draft'] : [];
        if ([] !== $last->toolResults) {
            return $this->afterTools($last, $draft, $request->context, $texts);
        }

        $calls = $this->script(Text::fold($last->content), $last->content, $draft, $request->context, $texts);
        if ([] === $calls) {
            return self::answer($texts['greeting'], $texts['replies']);
        }

        return LlmResponse::toolCalls(array_map(fn (array $c): LlmToolCall => new LlmToolCall('toolu_fake_'.(++$this->calls), $c[0], $c[1]), $calls));
    }

    /**
     * The tool calls the user's message asks for ([] = just answer).
     *
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $context
     * @param array<string, mixed> $texts
     *
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    private function script(string $text, string $original, array $draft, array $context, array $texts): array
    {
        $item = \is_array($context['item'] ?? null) ? $context['item'] : null;
        $create = 'create' === ($context['mode'] ?? 'create');
        $yes = 1 === preg_match('/^(si|yes|ok|dale|claro|confirmo)\b/', $text);

        // The basics are set and the user confirms them: questions, ending and review in one go.
        if ($yes && !($draft['basics_confirmed'] ?? false) && null !== ($draft['title'] ?? null)) {
            $topic = (string) ($draft['topic'] ?? $draft['title']);
            $scored = 'diagnostic' === ($draft['type'] ?? null);
            $questions = array_map(static fn (string $q): array => [
                'title' => \sprintf($q, $topic),
                'type' => 'radio',
                'choices' => array_map(static fn (string $label, int $i): array => ['label' => $label, 'value' => $scored ? $i : null], $texts['choices'], array_keys($texts['choices'])),
                'category' => $scored ? 'General' : null,
            ], $texts['questions']);
            $ending = ['message' => $texts['thanks']];
            if ($scored) {
                $ending['tiers'] = array_map(static fn (array $t): array => ['name' => $t[0], 'description' => $t[1], 'recommendations' => [$t[2]], 'action_plan' => [$t[2]]], $texts['tiers']);
            }

            return [['confirm_basics', []], ['set_questions', ['questions' => $questions]], ['set_ending', $ending], ['request_review', []]];
        }
        // With the questions under way: "agrega una tabla" / "add a table", "agrega un archivo con plantilla" / "add a
        // file with a template".
        if (($draft['basics_confirmed'] ?? false) && 1 === preg_match('/(agrega|anade|add|incluye|include)\b.*\b(tabla|table|archivo|file)\b/', $text)) {
            $question = 1 === preg_match('/\b(tabla|table)\b/', $text)
                ? ['title' => $texts['table'][0], 'type' => 'table', 'columns' => $texts['table'][1], 'rows' => []]
                : ['title' => $texts['file'][0], 'type' => 'file', 'template' => 1 === preg_match('/plantilla|template|formato|format/', $text)
                    ? ['filename' => $texts['file'][1], 'columns' => $texts['file'][2], 'example_rows' => [$texts['file'][3]]]
                    : null];

            return [['add_questions', ['questions' => [$question]]], ['request_review', []]];
        }
        if (null !== $item && 1 === preg_match('/detalle|details|muestrame|show me/', $text)) {
            $kind = (string) ($item['kind'] ?? '');

            return [['get_'.$kind, [$kind.'_id' => (string) ($item['id'] ?? '')]]];
        }
        if (1 === preg_match('/(crea|create|haz|make|nuevo|new|quiero).*(cuestionario|questionnaire|encuesta|survey|diagnostico|diagnostic)/', $text)) {
            $topic = preg_match('/\b(?:sobre|about|de|on)\s+(.+)$/iu', $original, $m) ? trim($m[1], " .!?¿¡\t\n") : $texts['untitled'];
            $type = str_contains($text, 'diagnostic') ? 'diagnostic' : (str_contains($text, 'cadena') || str_contains($text, 'chain') ? 'chain' : 'regular');
            $basics = ['title' => mb_substr(mb_strtoupper(mb_substr($topic, 0, 1)).mb_substr($topic, 1), 0, 200), 'type' => $type, 'topic' => $topic, 'landing_page' => false, 'has_disclaimer' => false, 'capture_user_data' => false];
            if ('chain' === $type) {
                $basics['chain_prompt'] = $texts['chain_prompt'];
            }

            return [['update_draft', $basics]];
        }
        if (!$create) {
            return [];
        }
        $itemId = (string) ($item['id'] ?? '');
        $itemKind = (string) ($item['kind'] ?? '');
        if ('organization' === $itemKind && 1 === preg_match('/elimina|borra|delete|remove/', $text)) {
            return [['delete_organization', ['organization_id' => $itemId]]];
        }
        if ('questionnaire' === $itemKind) {
            if (1 === preg_match('/desactiva|deactivate/', $text)) {
                return [['set_questionnaire_active', ['questionnaire_id' => $itemId, 'is_active' => false]]];
            }
            if (1 === preg_match('/copia|duplica|copy|duplicate/', $text)) {
                return [['copy_questionnaire', ['questionnaire_id' => $itemId]]];
            }
            if (1 === preg_match('/edita|edit/', $text)) {
                return [['load_questionnaire', ['questionnaire_id' => $itemId]]];
            }
        }
        if (preg_match('/(?:crea|create|agrega|add)\s+(?:la |una |the |an? )?(?:organizacion|organization)\s+(.+)$/u', $text) && preg_match('/(?:organizaci[oó]n|organization)\s+(.+)$/iu', $original, $m)) {
            return [['create_organization', ['name' => trim($m[1], " .!?\"'«»“”")]]];
        }
        if (1 === preg_match('/idioma|language/', $text)) {
            return [['update_account_language', ['language' => 1 === preg_match('/ingles|english/', $text) ? 'en' : 'es']]];
        }
        if (1 === preg_match('/(estilos|styles|marca|brand)/', $text) && preg_match('#https?://\S+#i', $original, $m)) {
            return [['extract_brand_styles', ['website' => rtrim($m[0], '.,)')]]];
        }
        foreach (self::LISTS as $tool => $words) {
            foreach ($words as $word) {
                if (1 === preg_match('/\b'.$word.'\b/', $text)) {
                    $offset = 1 === preg_match('/(ver|see) 5 (mas|more)/', $text) ? 5 : 0;

                    return [[$tool, str_starts_with($tool, 'list_') && !\in_array($tool, ['list_plans', 'list_videos', 'list_webhooks', 'list_api_keys'], true) ? ['offset' => $offset] : []]];
                }
            }
        }

        return [];
    }

    /**
     * The answer once the tools ran.
     *
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $context
     * @param array<string, mixed> $texts
     */
    private function afterTools(LlmMessage $last, array $draft, array $context, array $texts): LlmResponse
    {
        $lines = [];
        $replies = [];
        foreach ($last->toolResults as $toolResult) {
            $content = trim((string) preg_replace('#^<tool_result>|</tool_result>$#', '', trim($toolResult->content)));
            $result = json_decode($content, true);
            $result = \is_array($result) ? $result : [];
            if (isset($result['error']) && \is_array($result['error'])) {
                $lines = [\sprintf($texts['error'], (string) ($result['error']['message'] ?? ''))];
                $replies = [];
                break;
            }
            if (true === ($result['queued'] ?? false)) {
                $lines[] = \sprintf($texts['queued'], (string) ($result['label'] ?? ''));
                $replies = [$texts['yes'], $texts['no']];
            } elseif (isset($result['rows']) && \is_array($result['rows'])) {
                $lines[] = [] === $result['rows'] ? $texts['empty'] : self::table($result['rows']);
                if (true === ($result['has_more'] ?? false)) {
                    $replies[] = $texts['more'];
                }
            } elseif (\array_key_exists('phase', $result)) {
                continue;
            } elseif ([] !== $result) {
                $lines[] = self::table([array_filter($result, static fn ($v): bool => \is_scalar($v) || null === $v)]);
            }
        }
        if ([] === $lines) {
            $title = (string) ($draft['title'] ?? '');
            if ('review' === ($draft['phase'] ?? null)) {
                $questions = array_map(static fn (array $q, int $i): string => ($i + 1).'. '.$q['title'], (array) $draft['questions'], array_keys((array) $draft['questions']));
                $lines[] = \sprintf('draft' === ($context['mode'] ?? null) ? $texts['review_draft'] : $texts['review'], $title, \count($questions), implode("\n", $questions));
                $replies = [$texts['yes'], $texts['no']];
            } elseif (null !== ($draft['title'] ?? null) && !($draft['basics_confirmed'] ?? false)) {
                $lines[] = \sprintf($texts['basics'], $title, $texts['types'][(string) ($draft['type'] ?? 'regular')] ?? '', (string) ($draft['topic'] ?? ''));
                $replies = [$texts['yes'], $texts['no']];
            } else {
                $lines[] = $texts['done'];
            }
        }

        return self::answer(implode("\n\n", $lines), $replies);
    }

    /** @param list<string> $quickReplies */
    private static function answer(string $message, array $quickReplies): LlmResponse
    {
        return LlmResponse::json(['message' => $message, 'quick_replies' => $quickReplies]);
    }

    /**
     * A Markdown table of rows, linking records by their id as [name](item:kind/id).
     *
     * @param array<int|string, mixed> $rows
     */
    private static function table(array $rows): string
    {
        $rows = array_values(array_filter($rows, 'is_array'));
        if ([] === $rows) {
            return '';
        }
        $first = $rows[0];
        $columns = \array_slice(array_values(array_filter(
            array_map('strval', array_keys($first)),
            static fn (string $k): bool => !str_ends_with($k, '_id') && 'id' !== $k && (null === $first[$k] || \is_scalar($first[$k])),
        )), 0, 4);
        $out = '| '.implode(' | ', $columns)." |\n|".str_repeat(' --- |', \count($columns));
        foreach ($rows as $row) {
            $cells = [];
            foreach ($columns as $i => $column) {
                $value = $row[$column] ?? '';
                $cell = \is_bool($value) ? ($value ? '✓' : '—') : str_replace('|', '/', (string) (\is_scalar($value) ? $value : ''));
                foreach (['questionnaire', 'organization', 'assignation', 'project'] as $kind) {
                    if (0 === $i && \is_string($row[$kind.'_id'] ?? null)) {
                        $cell = '['.$cell.'](item:'.$kind.'/'.$row[$kind.'_id'].')';
                        break;
                    }
                }
                $cells[] = $cell;
            }
            $out .= "\n| ".implode(' | ', $cells).' |';
        }

        return $out;
    }
}
