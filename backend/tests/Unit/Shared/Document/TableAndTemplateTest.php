<?php

namespace App\Tests\Unit\Shared\Document;

use App\Shared\Domain\Document\FileTemplate;
use App\Shared\Domain\Document\Questions;
use App\Shared\Domain\Document\TableAnswer;
use PHPUnit\Framework\TestCase;

/** A table question (columns, fixed rows, its answer) and a file question's template. */
final class TableAndTemplateTest extends TestCase
{
    private const UUID = '0f8fad5b-d9cb-469f-a165-70867728950e';

    public function testATablesColumnsAreItsOptionsKeyedByValueOrLabel(): void
    {
        $control = self::table([['label' => 'Name', 'value' => 'name'], ['label' => 'Role', 'value' => null], ['label' => 'Name again', 'value' => 'name'], ['label' => '', 'value' => '']]);

        self::assertSame(['name' => 'Name', 'Role' => 'Role'], TableAnswer::columns($control), 'a column without a value is keyed by its label; repeated and empty columns are dropped');
    }

    public function testAnAnswerKeepsOnlyKnownColumnsAndDropsEmptyRows(): void
    {
        $control = self::table([['label' => 'Name', 'value' => 'name'], ['label' => 'Email', 'value' => 'email']]);

        $clean = TableAnswer::clean([
            ['name' => ' Ana ', 'email' => 'ana@acme.test', 'evil' => 'x'],
            ['name' => '', 'email' => '  '],
            'not a row',
            ['name' => ['nested'], 'email' => 42],
        ], $control);

        self::assertSame([['name' => 'Ana', 'email' => 'ana@acme.test'], ['email' => '42']], $clean, 'unknown columns are dropped, cells trimmed, empty rows removed');
        self::assertNull(TableAnswer::clean([['name' => ' ']], $control), 'nothing filled in is no answer');
        self::assertNull(TableAnswer::clean('a string', $control), 'a table\'s value is a list of rows');
        self::assertCount(TableAnswer::MAX_ROWS, (array) TableAnswer::clean(array_fill(0, 80, ['name' => 'x']), $control), 'at most 50 rows');
    }

    public function testWithFixedRowsTheAnswerFollowsThemAndKeepsEmptyOnes(): void
    {
        $control = self::table([['label' => 'Sales', 'value' => 'sales']], ['January', 'February']);

        self::assertSame([[], ['sales' => '12']], TableAnswer::clean([['sales' => ''], ['sales' => '12'], ['sales' => 'extra row']], $control), 'a fixed row keeps its position; rows beyond the fixed ones are dropped');
        self::assertSame([['row' => 'January', 'Sales' => ''], ['row' => 'February', 'Sales' => '12']], TableAnswer::labelled([[], ['sales' => '12']], $control));
        self::assertSame('February — Sales: 12', TableAnswer::toText([[], ['sales' => '12']], $control));
    }

    public function testAnAnswerIsStructuredAsATableToPreviewAndNullWhenNothingIsFilledIn(): void
    {
        $control = self::table([['label' => 'Sales', 'value' => 'sales'], ['label' => 'Notes']], ['January', 'February']);

        self::assertSame([
            'columns' => [['key' => 'sales', 'label' => 'Sales'], ['key' => 'Notes', 'label' => 'Notes']],
            'rows' => [
                ['label' => 'January', 'cells' => ['sales' => '', 'Notes' => '']],
                ['label' => 'February', 'cells' => ['sales' => '12', 'Notes' => 'late']],
            ],
        ], TableAnswer::structured([[], ['sales' => '12', 'Notes' => 'late']], $control), 'every column, every row with its fixed label');
        self::assertSame(
            [['label' => null, 'cells' => ['sales' => '3', 'Notes' => '']]],
            TableAnswer::structured([['sales' => 3]], self::table([['label' => 'Sales', 'value' => 'sales'], ['label' => 'Notes']]))['rows'] ?? null,
            'rows the respondent added have no label',
        );
        self::assertNull(TableAnswer::structured([[], ['sales' => ' ']], $control), 'nothing filled in: no table');
        self::assertNull(TableAnswer::structured('text', $control));
    }

    public function testNormalizingKeepsRowsOnlyOnATableAndTheTemplateOnlyOnAFileQuestion(): void
    {
        $template = ['key' => 'templates/ACME0001/'.self::UUID.'/budget.xlsx', 'filename' => 'budget.xlsx'];

        $table = Questions::normalizeControl(['type' => 'table', 'options' => [['label' => 'A']], 'rows' => ['One', ' ', 'Two'], 'template' => $template]);
        $file = Questions::normalizeControl(['type' => 'file', 'rows' => ['One'], 'template' => $template]);
        $text = Questions::normalizeControl(['type' => 'text', 'rows' => ['One'], 'template' => $template]);

        self::assertSame(['One', 'Two'], $table['rows']);
        self::assertArrayNotHasKey('template', $table);
        self::assertSame($template, $file['template']);
        self::assertArrayNotHasKey('rows', $file);
        self::assertArrayNotHasKey('rows', $text);
        self::assertArrayNotHasKey('template', $text);
        self::assertSame([], Questions::values(['options' => [$table + ['value' => [['A' => 'x']]]]]), 'a table\'s rows are not values to score');
        self::assertTrue(Questions::isAnswered(['options' => [$table + ['value' => [['A' => 'x']]]]]), 'a filled-in table is an answer');
    }

    public function testATemplateIsAStoredKeyOrATextToStore(): void
    {
        self::assertSame(
            ['key' => 'templates/ACME0001/'.self::UUID.'/budget.xlsx', 'filename' => 'Budget 2026.xlsx'],
            FileTemplate::normalize(['key' => 'templates/ACME0001/'.self::UUID.'/budget.xlsx', 'filename' => 'Budget 2026.xlsx']),
        );
        self::assertSame(['filename' => 'plan.csv', 'text' => "a,b\n"], FileTemplate::normalize(['filename' => 'plan.csv', 'text' => "a,b\n"]));
        self::assertNull(FileTemplate::normalize(['key' => 'ACME0001/s/q/answer.pdf']), 'an answer\'s file is not a template');
        self::assertNull(FileTemplate::normalize(['key' => 'templates/ACME0001/'.self::UUID.'/../x']), 'no ".."');
        self::assertNull(FileTemplate::normalize(['filename' => 'x.csv', 'text' => '  ']), 'an empty text is no template');
        self::assertNull(FileTemplate::normalize('templates/x'));
    }

    public function testATemplatesKeyNamesItsAccountAndKeepsASafeFileName(): void
    {
        $key = FileTemplate::keyFor('ACME0001', self::UUID, '../Presupuesto año 2026 (v2).xlsx');

        self::assertSame('templates/ACME0001/'.self::UUID.'/Presupuesto ano 2026 -v2-.xlsx', $key, 'accents transliterated, path and symbols removed');
        self::assertSame('ACME0001', FileTemplate::customerOf($key));
        self::assertNull(FileTemplate::customerOf('prompts/ACME0001/'.self::UUID.'.txt'));
        self::assertSame('template', FileTemplate::filename('///', 'template'));
        self::assertLessThanOrEqual(FileTemplate::FILENAME_MAX, \strlen(FileTemplate::filename(str_repeat('a', 300).'.csv', 'x')));
        self::assertStringEndsWith('.csv', FileTemplate::filename(str_repeat('a', 300).'.csv', 'x'), 'a long name keeps its extension');
    }

    public function testATemplateWrittenByTheChatIsACsvWithItsHeaderAndExamples(): void
    {
        $csv = FileTemplate::csv(['Item', 'Cost, USD'], [['Licenses', '500'], ['Only one cell']]);

        self::assertSame("\u{FEFF}Item,\"Cost, USD\"\nLicenses,500\n\"Only one cell\",\n", $csv, 'a BOM for Excel, quoted cells, short rows padded');
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @param list<string>               $rows
     *
     * @return array<string, mixed>
     */
    private static function table(array $columns, array $rows = []): array
    {
        return ['type' => 'table', 'options' => $columns, 'rows' => $rows, 'validations' => []];
    }
}
