<?php

namespace App\Shared\Domain\Document;

/**
 * A table question: the control's options are its columns (label, and the value that keys a cell), its `rows` the
 * optional fixed row labels. Without fixed rows the respondent adds rows, up to MAX_ROWS.
 *
 * The answer (the control's value) is a list of rows, each `{column key: text}`; with fixed rows the list follows
 * their order. Whatever a respondent sends is reduced to that shape: unknown columns are dropped, cells capped,
 * empty rows removed (fixed rows keep their position, so an empty one stays as {}).
 */
final class TableAnswer
{
    public const MAX_COLUMNS = 20;
    public const MAX_ROWS = 50;
    public const CELL_MAX = 1_000;
    public const ROW_LABEL_MAX = 200;

    /**
     * The columns of a table control: [key => label], in order. A column without a value is keyed by its label.
     *
     * @param array<string, mixed> $control
     *
     * @return array<string, string>
     */
    public static function columns(array $control): array
    {
        $columns = [];
        foreach ((array) ($control['options'] ?? []) as $option) {
            if (!\is_array($option)) {
                continue;
            }
            $label = trim((string) ($option['label'] ?? ''));
            $value = $option['value'] ?? null;
            $key = \is_scalar($value) && '' !== trim((string) $value) ? (string) $value : $label;
            if ('' === $key || isset($columns[$key])) {
                continue;
            }
            $columns[$key] = '' === $label ? $key : $label;
            if (\count($columns) >= self::MAX_COLUMNS) {
                break;
            }
        }

        return $columns;
    }

    /**
     * The fixed row labels of a control's `rows` (empty: the respondent adds rows).
     *
     * @return list<string>
     */
    public static function rowLabels(mixed $rows): array
    {
        $labels = [];
        foreach (\is_array($rows) ? $rows : [] as $row) {
            if (!\is_scalar($row)) {
                continue;
            }
            $label = trim(mb_substr(trim((string) $row), 0, self::ROW_LABEL_MAX));
            if ('' !== $label) {
                $labels[] = $label;
            }
            if (\count($labels) >= self::MAX_ROWS) {
                break;
            }
        }

        return $labels;
    }

    /**
     * A respondent's answer in the stored shape, or null when nothing is filled in.
     *
     * @param array<string, mixed> $control
     *
     * @return list<array<string, string>>|null
     */
    public static function clean(mixed $value, array $control): ?array
    {
        if (!\is_array($value)) {
            return null;
        }
        $keys = array_keys(self::columns($control));
        $fixed = \count(self::rowLabels($control['rows'] ?? []));
        $rows = [];
        foreach (array_values($value) as $i => $raw) {
            if (0 !== $fixed && $i >= $fixed) {
                break;
            }
            $row = [];
            foreach ($keys as $key) {
                $cell = \is_array($raw) ? ($raw[$key] ?? null) : null;
                if (\is_string($cell) || \is_int($cell) || \is_float($cell)) {
                    $text = trim(mb_substr((string) $cell, 0, self::CELL_MAX));
                    if ('' !== $text) {
                        $row[$key] = $text;
                    }
                }
            }
            if ([] !== $row || 0 !== $fixed) {
                $rows[] = $row;
            }
            if (\count($rows) >= self::MAX_ROWS) {
                break;
            }
        }
        foreach ($rows as $row) {
            if ([] !== $row) {
                return $rows;
            }
        }

        return null;
    }

    /**
     * The answer as rows keyed by column label, the fixed row's label under "row" (the webhook and the API, §7.14).
     *
     * @param array<string, mixed> $control
     *
     * @return list<array<string, string>>
     */
    public static function labelled(mixed $value, array $control): array
    {
        $columns = self::columns($control);
        $labels = self::rowLabels($control['rows'] ?? []);
        $out = [];
        foreach (\is_array($value) ? array_values($value) : [] as $i => $row) {
            if (!\is_array($row)) {
                continue;
            }
            $labelled = isset($labels[$i]) ? ['row' => $labels[$i]] : [];
            foreach ($columns as $key => $label) {
                $labelled[$label] = \is_scalar($row[$key] ?? null) ? (string) $row[$key] : '';
            }
            $out[] = $labelled;
        }

        return $out;
    }

    /**
     * One line per row, "Column: value; Column: value" (a sheet's cell, an LLM's context).
     *
     * @param array<string, mixed> $control
     */
    public static function toText(mixed $value, array $control): string
    {
        $lines = [];
        foreach (self::labelled($value, $control) as $row) {
            $prefix = isset($row['row']) ? $row['row'].' — ' : '';
            unset($row['row']);
            $cells = [];
            foreach ($row as $label => $cell) {
                if ('' !== $cell) {
                    $cells[] = $label.': '.$cell;
                }
            }
            if ([] !== $cells) {
                $lines[] = $prefix.implode('; ', $cells);
            }
        }

        return implode("\n", $lines);
    }
}
