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
 * - "crea un cuestionario sobre X" / "create a questionnaire about X" (a "diagnóstico" or a "cadena" too: the chat
 *   creates regular questionnaires only) → update_draft with the basics; then "sí" → confirm_basics, three questions,
 *   the ending and request_review;
 * - with a draft, "etiquétalo AP-03 y NP-12" / "tag it AP-03, NP-12" → update_draft with the draft's tags plus those;
 * - a message with an attached document, or with three or more questions pasted → update_draft with its title; then
 *   "sí" → its questions (see DocumentQuestions)
 *   instead of the three; with the questions under way, its questions are added;
 * - with a draft, "el título que sea X" / "ponle de título X" / "llámalo X" / "change the title to X" (before the basics
 *   are confirmed, "ponle X" alone too) → update_draft with that
 *   basic (the basics are shown again to confirm); anything else it doesn't follow → it asks again, keeping the draft;
 * - with the questions under way, "agrega una tabla" / "add a table" and "agrega un archivo con plantilla" / "add a
 *   file with a template" → add_questions (a table, a file question with a CSV template) and request_review;
 * - "mis cuestionarios / organizaciones / asignaciones / proyectos / videos / usuarios" → the list
 *   tools;
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
            'greeting' => '¡Hola! Puedo crear cuestionarios contigo y ayudarte con tu cuenta: organizaciones, asignaciones, proyectos y más. ¿Qué quieres hacer?',
            'replies' => ['Crear un cuestionario', 'Ver mis cuestionarios', 'Ver mis organizaciones'],
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
            'columns' => ['#', 'Pregunta', 'Tipo'],
            'controls' => ['radio' => 'Opción única', 'checkbox' => 'Opción múltiple', 'select' => 'Lista desplegable', 'text' => 'Texto', 'range' => 'Escala', 'table' => 'Tabla', 'file' => 'Archivo'],
            'untitled' => 'Mi cuestionario',
            'not_understood' => 'No entendí qué quieres cambiar del borrador. Dime, por ejemplo, «ponle de título …» o «cambia el tema a …», o responde «Sí» para confirmar los datos básicos.',
            'table' => ['¿Quiénes integran tu equipo?', ['Nombre', 'Cargo', 'Correo']],
            'file' => ['Sube tu presupuesto con la plantilla', 'plantilla-presupuesto.csv', ['Concepto', 'Cantidad', 'Costo'], ['Licencias', '10', '500']],
        ],
        'en' => [
            'greeting' => 'Hi! I can build questionnaires with you and help with your account: organizations, assignations, projects and more. What would you like to do?',
            'replies' => ['Create a questionnaire', 'See my questionnaires', 'See my organizations'],
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
            'columns' => ['#', 'Question', 'Type'],
            'controls' => ['radio' => 'Single choice', 'checkbox' => 'Multiple choice', 'select' => 'Dropdown', 'text' => 'Text', 'range' => 'Scale', 'table' => 'Table', 'file' => 'File'],
            'untitled' => 'My questionnaire',
            'not_understood' => 'I didn\'t get what to change in the draft. Tell me, for example, “set the title to …” or “change the topic to …”, or answer “Yes” to confirm the basics.',
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

        // What the user typed in every message (without the attached documents), for the questions they pasted.
        $typed = array_values(array_map(static fn (LlmMessage $m): string => self::withoutFiles($m->content), array_filter($request->messages, static fn (LlmMessage $m): bool => 'user' === $m->role && [] === $m->toolResults)));
        $calls = $this->script(Text::fold($last->content), $last->content, $draft, $request->context + ['typed' => $typed], $texts);
        if ([] === $calls) {
            // A draft under way is never dropped for the greeting.
            if (null !== ($draft['title'] ?? null) && !($draft['basics_confirmed'] ?? false)) {
                return self::answer($texts['not_understood'], [$texts['yes']]);
            }

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

        $files = array_values(array_filter(\is_array($context['files'] ?? null) ? $context['files'] : [], 'is_array'));
        $fromFiles = array_merge(...array_map(static fn (array $f): array => DocumentQuestions::in((string) ($f['text'] ?? '')), $files));
        $attached = str_contains($original, '<attached_file>');
        // Questions pasted in the message: three or more lines that read as questions.
        $pastedNow = DocumentQuestions::in(self::withoutFiles($original));
        $pasted = \count($pastedNow) >= 3;
        if ([] === $fromFiles) {
            $fromFiles = array_merge(...array_map(static fn (string $typed): array => \count(DocumentQuestions::in($typed)) >= 3 ? DocumentQuestions::in($typed) : [], (array) ($context['typed'] ?? [])));
        }

        // The basics are set and the user confirms them: questions, ending and review in one go.
        if ($yes && !($draft['basics_confirmed'] ?? false) && null !== ($draft['title'] ?? null)) {
            $topic = (string) ($draft['topic'] ?? $draft['title']);
            $scored = 'diagnostic' === ($draft['type'] ?? null);
            $questions = [] !== $fromFiles ? \array_slice($fromFiles, 0, 100) : array_map(static fn (string $q): array => [
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
        // A document attached, or questions pasted: before the basics, they come from it; with the questions under way,
        // its questions are added.
        if (($attached && [] !== $files) || $pasted) {
            if ($draft['basics_confirmed'] ?? false) {
                $added = $pasted ? $pastedNow : $fromFiles;

                return [] === $added ? [] : [['add_questions', ['questions' => \array_slice($added, 0, 100)]], ['request_review', []]];
            }
            $title = self::titleOf($attached && [] !== $files ? $files[\count($files) - 1] : ['text' => self::withoutFiles($original)]);

            return [['update_draft', ['title' => $title, 'type' => 'regular', 'topic' => $title, 'landing_page' => false, 'has_disclaimer' => false, 'capture_user_data' => false]]];
        }
        // With a draft: "etiquétalo AP-03 y NP-12", "tag it AP-03".
        if (null !== ($draft['title'] ?? null) && null !== ($tags = self::tagsAsked($original))) {
            return [['update_draft', ['tags' => array_values(array_merge(array_filter((array) ($draft['tags'] ?? []), 'is_string'), $tags))]]];
        }
        // With a draft: "el título que sea X", "cambia el tema a X", "change the title to X".
        if (null !== ($draft['title'] ?? null) && null !== ($basic = self::basicChange($original, !($draft['basics_confirmed'] ?? false)))) {
            return [['update_draft', $basic]];
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
            $title = $topic;
            // "llamado X sobre Y" / "called X about Y": the name is the title, the rest the topic.
            if (1 === preg_match('/\b(?:llamad[oa]|titulad[oa]|called|named|titled)\s+(.+?)(?:\s+(?:sobre|about|de|on)\s+(.+))?$/iu', $original, $m)) {
                $title = trim($m[1], " .!?¿¡«»\"'\t\n");
                $topic = isset($m[2]) ? trim($m[2], " .!?¿¡\t\n") : $title;
            }
            $basics = ['title' => mb_substr(mb_strtoupper(mb_substr($title, 0, 1)).mb_substr($title, 1), 0, 200), 'type' => 'regular', 'topic' => $topic, 'landing_page' => false, 'has_disclaimer' => false, 'capture_user_data' => false];

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

                    return [[$tool, str_starts_with($tool, 'list_') && !\in_array($tool, ['list_videos'], true) ? ['offset' => $offset] : []]];
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
                $questions = array_values((array) $draft['questions']);
                $lines[] = \sprintf('draft' === ($context['mode'] ?? null) ? $texts['review_draft'] : $texts['review'], $title, \count($questions), self::questionTable($questions, $texts));
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

    /**
     * The draft's questions as the review shows them: a Markdown table # | Question | Type.
     *
     * @param list<mixed>          $questions
     * @param array<string, mixed> $texts
     */
    private static function questionTable(array $questions, array $texts): string
    {
        $out = '| '.implode(' | ', $texts['columns'])." |\n| --- | --- | --- |";
        foreach ($questions as $i => $question) {
            $question = \is_array($question) ? $question : [];
            $type = (string) ($question['type'] ?? '');
            $out .= "\n| ".($i + 1).' | '.str_replace(['|', "\n"], ['/', ' '], (string) ($question['title'] ?? '')).' | '.($texts['controls'][$type] ?? $type).' |';
        }

        return $out;
    }

    /**
     * The basic the user asks to change, in their words: "el título que sea X", "cambia el tema a X", "title: X",
     * "ponle de título X", "set the title X", "llámalo X"; with $bare (the basics are not confirmed yet) a verb alone
     * names the title too: "ponle X", "cámbialo a X", "make it X".
     *
     * @return array<string, string>|null
     */
    private static function basicChange(string $original, bool $bare = false): ?array
    {
        $typed = self::withoutFiles($original);
        $field = '(t[ií]tulo|title|nombre|name|tema|topic)';
        $of = '(?:\s+(?:del|de|of the|of)\s+(?:cuestionario|questionnaire|borrador|draft|encuesta|survey))?';
        $connector = '(?:[:=]|(?:que\s+)?(?:sea|ser[aá]|debe\s+ser|es|por|a|como|should\s+be|must\s+be|be|is|to|as)\b)';
        // A verb that asks for a change ("ponle", "pongle", "cambia", "set"…): then the field needs no connector.
        $verb = '\b(?:p[oó]n\w*|cambi\w*|c[aá]mbi\w*|modific\w*|actualiz\w*|us[ae]|dej\w*|escrib\w*|set|change|update|make|put|use|give)\b';
        $filler = '(?:\s+(?:de|del|como|el|la|su|un|nuevo|nueva|otro|the|a|an|as|its|it|new))*';
        $naming = '\b(?:ll[aá]m(?:alo|ala|elo|ele|ese)|que\s+se\s+llame|renombr\w*|call\s+it|name\s+it|rename\s+it)\b(?:\s+(?:a|como|to|as))?';

        if (1 === preg_match('/\b'.$field.'\b'.$of.'\s*'.$connector.'\s*(.+)$/iu', $typed, $m)
            || 1 === preg_match('/'.$verb.$filler.'\s+'.$field.'\b'.$of.'\s*(?:'.$connector.')?\s*(.+)$/iu', $typed, $m)) {
            [$name, $value] = [$m[1], $m[2]];
        } elseif (1 === preg_match('/'.$naming.'\s*(.+)$/iu', $typed, $m)) {
            [$name, $value] = ['title', $m[1]];
        } elseif ($bare && 1 === preg_match('/^\s*(?:p[oó]n(?:le|lo|la|gle)?|c[aá]mbi(?:a|ale|alo)(?:\s+(?:a|por))?|make\s+it|change\s+it\s+to)\s+(.+)$/iu', $typed, $m)) {
            // "ponle Cuestionario 4 oct" before the basics are confirmed: the field is the title.
            [$name, $value] = ['title', $m[1]];
        } else {
            return null;
        }
        $value = trim((string) preg_replace('/[\s,]*(?:por\s+favor|please|pls|porfa)[\s.!]*$/iu', '', $value), " .!?¿¡\t\n\"'«»“”");
        if ('' === $value) {
            return null;
        }
        $field = 1 === preg_match('/^(tema|topic)$/iu', $name) ? 'topic' : 'title';

        return [$field => mb_substr(mb_strtoupper(mb_substr($value, 0, 1)).mb_substr($value, 1), 0, 'title' === $field ? 200 : 2000)];
    }

    /**
     * The tags the user asks for: "etiquétalo AP-03 y NP-12", "tag it AP-03, NP-12", "etiquetas: AP-03; Some".
     *
     * @return list<string>|null
     */
    private static function tagsAsked(string $original): ?array
    {
        if (1 !== preg_match('/\b(?:tag(?:s|ged)?|etiqu\p{L}*)\b(?:\s+(?:it|them|with|as|lo|la|con|como|de))*\s*:?\s*(.+)$/iu', self::withoutFiles($original), $m)) {
            return null;
        }
        $tags = array_values(array_filter(array_map(
            static fn (string $t): string => trim($t, " .!?¿¡\t\n\"'«»“”"),
            preg_split('/\s*(?:[,;]|\s(?:y|and|e)\s)\s*/u', $m[1]) ?: [],
        ), static fn (string $t): bool => '' !== $t));

        return [] === $tags ? null : $tags;
    }

    /**
     * A document's title: its first heading, else its first line that is not a question, else its file name. A list
     * marker goes and only its first sentence stays ("- Cuestionario X. CONSIDERACIONES: 1. …" → "Cuestionario X").
     *
     * @param array<string, mixed> $file
     */
    private static function titleOf(array $file): string
    {
        foreach (preg_split('/\n/', (string) ($file['text'] ?? '')) ?: [] as $line) {
            $line = trim($line);
            if ('' === $line || str_ends_with($line, '?') || 1 === preg_match('/^\d{1,3}\s*[.)]/', $line)) {
                continue;
            }
            $line = trim((string) preg_replace('/^(?:#+|[-*•])\s*/u', '', $line));
            $sentence = trim(preg_split('/(?<=\S)[.:]\s+/u', $line, 2)[0] ?? $line);
            if ('' !== $sentence) {
                return mb_substr(rtrim($sentence, ' .:'), 0, 200);
            }
        }

        return mb_substr(pathinfo((string) ($file['filename'] ?? ''), \PATHINFO_FILENAME) ?: 'Cuestionario', 0, 200);
    }

    /** A user message without the <attached_file> blocks in front of it: what the user typed. */
    private static function withoutFiles(string $content): string
    {
        return trim((string) preg_replace('#<attached_file>.*?</attached_file>#su', '', $content));
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
