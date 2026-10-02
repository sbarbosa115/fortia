<?php

namespace App\Chat\Infrastructure\Llm;

/**
 * The questions of a document the way a person would spot them, for the offline assistant (ChatResponder). It reads
 * the text FileDocumentText makes of a Word file (list items as "- ", headings as "# ", table rows as "a | b"), a
 * Markdown or a pasted text:
 *
 * - a question is a numbered line ("1.", "2)", "Q3:", "**4.**"), a line that asks ("…?", "¿…"), or — when the
 *   document's questions are list items (Word's automatic numbering) — a list item that is long or ends in ":";
 * - its choices are the lines under it marked "a)", "-", "•", "( )", "[ ]" or "☐" (several "☐" on one line too), short
 *   plain lines ("Se usará nombre"), or a table under it: one of short cells ("Si | No", a grid of options) is radio,
 *   one whose rows name the options (with a description or a box to tick) is checkbox; "several" or "only one" in the
 *   question wins;
 * - a table with a header and empty rows to fill in is a table question with those columns (titled by its section
 *   when no question introduces it); a table whose header asks ("¿A quién le llega el aviso? | Indique") is a question
 *   of its section, its rows the choices;
 * - headings ("# …", "1. ALL CAPS", "1.8.2 …") start a section; what comes before the section of the first question
 *   that asks (instructions, considerations) is left out.
 */
final class DocumentQuestions
{
    /** A choice (or a cell of a choice table) is at most this long. */
    private const SHORT = 60;
    private const MAX_CHOICES = 20;
    private const MAX_COLUMNS = 20;
    private const MAX_ROWS = 50;
    private const BOX = '/[☐□■☑✓]/u';
    private const MULTI = '/m[aá]s de una|varias|todas las que|selecciona las opciones|more than one|all that apply|select all|several/iu';
    private const SINGLE = '/s[oó]lo (?:seleccionar |selecciona |elegir |elige )?una|una sola|only one|just one/iu';

    /**
     * @return list<array<string, mixed>> draft questions: {title, type: text}, {title, type: radio|checkbox, choices},
     *                                    {title, type: table, columns, rows}
     */
    public static function in(string $text): array
    {
        $lines = self::lines($text);
        $listQuestions = \count(array_filter($lines, static fn (array $l): bool => $l['list'] && !$l['row'] && self::asks($l['text']))) >= 2;

        /** @var list<array{title: string, choices: list<string>, columns: list<string>, rows: list<string>, multi: bool|null, section: int, asks: bool}> $found */
        $found = [];
        $open = null;
        $section = 0;
        $heading = null;
        $count = \count($lines);
        for ($i = 0; $i < $count; ++$i) {
            $line = $lines[$i];
            $text = $line['text'];
            if ('' === $text) {
                continue;
            }
            if ($line['heading']) {
                $heading = $text;
                $open = null;
                ++$section;
                continue;
            }

            if ($line['row']) {
                // The table: its rows, then the plain short lines right under it (rows whose other cells were empty).
                $rows = [];
                $divider = false;
                $ticks = false;
                for (; $i < $count && ($lines[$i]['row'] || ([] !== $rows && self::looseRow($lines[$i]))); ++$i) {
                    if ($lines[$i]['divider']) {
                        $divider = true;
                    } else {
                        $rows[] = $lines[$i]['row'] ? $lines[$i]['cells'] : [$lines[$i]['text']];
                        $ticks = $ticks || 1 === preg_match(self::BOX, $lines[$i]['text']);
                    }
                }
                --$i;
                $table = self::table($rows, $divider, $ticks);
                if (null === $table) {
                    continue;
                }
                if ('asks' === $table['kind']) {
                    $found[] = ['choices' => $table['choices'], 'multi' => false] + self::question((null !== $heading ? $heading.': ' : '').$table['title'], $section);
                    $open = null;
                    continue;
                }
                if (null !== $open && [] === $found[$open]['choices'] && [] === $found[$open]['columns']) {
                    if ('fill' === $table['kind']) {
                        $found[$open]['columns'] = $table['columns'];
                        $found[$open]['rows'] = $table['rows'];
                    } else {
                        $found[$open]['choices'] = $table['choices'];
                        $found[$open]['multi'] ??= 'options' === $table['kind'];
                    }
                } elseif (null === $open && 'fill' === $table['kind'] && null !== $heading) {
                    $found[] = ['columns' => $table['columns'], 'rows' => $table['rows']] + self::question($heading, $section);
                }
                $open = null;
                continue;
            }

            $boxes = 1 === preg_match(self::BOX, $text);
            $before = $boxes ? trim((string) preg_split(self::BOX, $text, 2)[0]) : $text;
            $short = mb_strlen($text) <= self::SHORT;
            $marked = 1 === preg_match('/^(?:[a-h][.)]|\(\s?\)|\[\s?\]|[○•])\s*(.+)$/iu', $text, $mark);

            // A choice of the open question.
            if (null !== $open && [] === $found[$open]['columns'] && !$line['numbered'] && !self::asks($before)) {
                if ($boxes && '' === $before || $marked) {
                    $found[$open]['choices'] = [...$found[$open]['choices'], ...($marked ? [self::label($mark[1])] : self::boxed($text))];
                    continue;
                }
                if ($short && ($line['list'] || self::plainChoice($text)) && !str_ends_with($text, ':')) {
                    $found[$open]['choices'][] = self::label($text);
                    continue;
                }
            }

            $isQuestion = $line['numbered'] || self::asks($before)
                || ($listQuestions && $line['list'] && mb_strlen($text) >= 15 && (!$short || str_ends_with($text, ':') || null === $open));
            if ($isQuestion && '' !== $before) {
                $found[] = self::question($before, $section);
                $open = \count($found) - 1;
                if ($boxes) {
                    $found[$open]['choices'] = self::boxed(mb_substr($text, mb_strlen($before)));
                }
                continue;
            }
            // Prose after the choices ends the question.
            if (null !== $open && ([] !== $found[$open]['choices'] || [] !== $found[$open]['columns'])) {
                $open = null;
            }
        }

        // The instructions before the section of the first question that asks are not questions.
        $first = array_values(array_filter($found, static fn (array $q): bool => $q['asks']))[0]['section'] ?? 0;
        $found = array_values(array_filter($found, static fn (array $q): bool => $q['section'] >= $first));

        return array_map(self::draft(...), $found);
    }

    /**
     * The document's lines, cleaned of Markdown and HTML, each with what it is.
     *
     * @return list<array{text: string, heading: bool, list: bool, numbered: bool, row: bool, divider: bool, cells: list<string>}>
     */
    private static function lines(string $text): array
    {
        $text = (string) preg_replace('#<!--\s*Start of picture text\s*-->.*?<!--\s*End of picture text\s*-->#su', "\n", $text);
        $text = (string) preg_replace('#<!--.*?-->#su', '', $text);
        $text = (string) preg_replace('#<br\s*/?>#iu', ' ', $text);
        $text = (string) preg_replace('#~~.*?~~#u', '', $text);
        $text = strip_tags(str_replace(['**', '__'], '', $text));

        $lines = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $raw) {
            $line = trim((string) preg_replace('/(^|\s)_([^_\s][^_]*)_(?=\s|$)/u', '$1$2', trim($raw)));
            $heading = 1 === preg_match('/^#{1,6}\s*(.*)$/u', $line, $m);
            if ($heading) {
                $line = trim($m[1]);
            }
            $list = !$heading && 1 === preg_match('/^[-*•]\s+(.*)$/u', $line, $m);
            if ($list) {
                $line = trim($m[1]);
            }
            $line = trim((string) preg_replace(['/¿\s+/u', '/\s+/u'], ['¿', ' '], $line));
            $row = str_contains($line, '|');
            $cells = $row ? array_map(static fn (string $c): string => trim((string) preg_replace(self::BOX, '', $c)), explode('|', self::innerRow($line))) : [];
            $divider = $row && 1 === preg_match('/^\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?$/', $line);

            // Multi-level numbers ("1.8.2 Avisos") and numbered capitals ("3. ORGANIGRAMA") are section headings.
            $numbered = false;
            if (!$heading && !$row && 1 === preg_match('/^\d{1,3}(?:\.\d{1,3})+\.?\s+(.+)$/u', $line, $m)) {
                [$heading, $line] = [true, trim($m[1])];
            } elseif (!$heading && !$row && 1 === preg_match('/^(?:(?:q|pregunta|question)\s*)?\d{1,3}\s*[.):-]\s*(.+)$/iu', $line, $m)) {
                $rest = trim($m[1]);
                if (mb_strtoupper($rest) === $rest && 1 === preg_match('/\p{L}/u', $rest)) {
                    [$heading, $line] = [true, $rest];
                } else {
                    [$numbered, $line] = [true, $rest];
                }
            }

            $lines[] = ['text' => $line, 'heading' => $heading && '' !== $line, 'list' => $list, 'numbered' => $numbered, 'row' => $row, 'divider' => $divider, 'cells' => $cells];
        }

        return $lines;
    }

    /** "| a | b |" (Markdown) without its outer bars; "a |" (a Word row with its last cell empty) as it is. */
    private static function innerRow(string $line): string
    {
        return mb_strlen($line) > 1 && str_starts_with($line, '|') && str_ends_with($line, '|') ? substr($line, 1, -1) : $line;
    }

    /**
     * What a table is: choices of short cells, options named by its rows, a table to fill in, or a question (its header
     * asks). `$ticks`: it had boxes to tick ("☐"), gone from its cells.
     *
     * @param list<list<string>> $rows
     *
     * @return array{kind: 'choices'|'options', choices: list<string>}|array{kind: 'fill', columns: list<string>, rows: list<string>}|array{kind: 'asks', title: string, choices: list<string>}|null
     */
    private static function table(array $rows, bool $divider, bool $ticks): ?array
    {
        $filled = array_map(static fn (array $cells): array => array_values(array_filter($cells, static fn (string $c): bool => '' !== $c)), $rows);
        $header = $filled[0] ?? [];
        if ([] === $header) {
            return null;
        }
        $data = \array_slice($filled, 1);
        $firsts = array_values(array_filter(array_map(static fn (array $cells): string => self::label($cells[0] ?? ''), $data), static fn (string $c): bool => '' !== $c && !str_ends_with($c, ':')));

        if (self::asks($header[0])) {
            return [] === $firsts ? null : ['kind' => 'asks', 'title' => $header[0], 'choices' => $firsts];
        }
        $shortCells = static fn (array $cells): bool => [] === array_filter($cells, static fn (string $c): bool => mb_strlen($c) > 40);
        // "Si | No", or a grid of options: every row full of short cells.
        if (!$divider && [] === array_filter($filled, static fn (array $cells): bool => \count($cells) < 2 || !$shortCells($cells))) {
            return ['kind' => 'choices', 'choices' => array_map(self::label(...), array_merge(...$filled))];
        }
        if (\count($header) < 2) {
            return null;
        }
        $onlyFirst = [] === array_filter($data, static fn (array $cells): bool => \count($cells) > 1);
        $sameShape = [] === array_filter(\array_slice($rows, 1), static fn (array $cells): bool => \count($cells) !== \count($rows[0]));
        // A header and rows to fill in (empty, or naming each row).
        if ([] === $firsts || ($onlyFirst && $sameShape && !$ticks)) {
            // With rows named in the first column, its header ("Evento") names the rows: it is not a column to fill.
            $columns = [] !== $firsts && '' !== ($rows[0][0] ?? '') ? \array_slice($header, 1) : $header;

            return ['kind' => 'fill', 'columns' => \array_slice(array_map(self::label(...), $columns), 0, self::MAX_COLUMNS), 'rows' => \array_slice($firsts, 0, self::MAX_ROWS)];
        }

        return ['kind' => 'options', 'choices' => $firsts];
    }

    /** A plain line under a question that reads as an option: short, not a sentence, not a page number. */
    private static function plainChoice(string $text): bool
    {
        return !str_ends_with($text, '.') && 1 !== preg_match('/^\d+$/', $text) && 1 !== preg_match('/^(nota|note|ejemplo|example)\b/iu', $text);
    }

    /**
     * A line under a table that is one of its rows (a row whose other cells were empty).
     *
     * @param array{text: string, heading: bool, list: bool, numbered: bool} $line
     */
    private static function looseRow(array $line): bool
    {
        return '' !== $line['text'] && !$line['heading'] && !$line['numbered'] && mb_strlen($line['text']) <= ($line['list'] ? self::SHORT : 80) && !self::asks($line['text']) && !str_ends_with($line['text'], ':');
    }

    private static function asks(string $text): bool
    {
        return mb_strlen($text) >= 8 && (str_ends_with($text, '?') || str_starts_with($text, '¿'));
    }

    /** @return list<string> the choices of a line like "☐ Si ☐ No" */
    private static function boxed(string $text): array
    {
        return array_values(array_filter(array_map(self::label(...), preg_split(self::BOX, $text) ?: []), static fn (string $c): bool => '' !== $c));
    }

    private static function label(string $text): string
    {
        return mb_substr(trim((string) preg_replace(self::BOX, '', $text), " \t.;,"), 0, 300);
    }

    /** @return array{title: string, choices: list<string>, columns: list<string>, rows: list<string>, multi: bool|null, section: int, asks: bool} */
    private static function question(string $title, int $section): array
    {
        $title = mb_substr(trim($title), 0, 500);
        $multi = 1 === preg_match(self::SINGLE, $title) ? false : (1 === preg_match(self::MULTI, $title) ? true : null);

        return ['title' => $title, 'choices' => [], 'columns' => [], 'rows' => [], 'multi' => $multi, 'section' => $section, 'asks' => self::asks($title)];
    }

    /**
     * @param array{title: string, choices: list<string>, columns: list<string>, rows: list<string>, multi: bool|null, section: int, asks: bool} $q
     *
     * @return array<string, mixed>
     */
    private static function draft(array $q): array
    {
        if ([] !== $q['columns']) {
            return ['title' => $q['title'], 'type' => 'table', 'columns' => $q['columns'], 'rows' => $q['rows']];
        }
        $choices = \array_slice(array_values(array_unique(array_filter($q['choices'], static fn (string $c): bool => '' !== $c))), 0, self::MAX_CHOICES);
        if (\count($choices) < 2) {
            return ['title' => $q['title'], 'type' => 'text'];
        }

        return ['title' => $q['title'], 'type' => true === $q['multi'] ? 'checkbox' : 'radio', 'choices' => array_map(static fn (string $c): array => ['label' => $c, 'value' => null], $choices)];
    }
}
