<?php

namespace App\Tests\Unit\Chat;

use App\Chat\Application\Document\AttachedFiles;
use App\Chat\Domain\AttachedFile;
use App\Chat\Domain\Error\UnreadableFile;
use App\Chat\Infrastructure\Document\FileDocumentText;
use App\Tests\Support\DocumentFixtures;
use PHPUnit\Framework\TestCase;

/**
 * A document attached to the chat to build a questionnaire from it: which files are read, how their text comes out,
 * and what is refused.
 */
final class AttachedFileTest extends TestCase
{
    use DocumentFixtures;

    private AttachedFiles $files;

    protected function setUp(): void
    {
        $this->files = new AttachedFiles(new FileDocumentText());
    }

    public function testAWordDocumentIsReadParagraphByParagraphWithItsHeadingsListsAndTables(): void
    {
        $file = $this->files->read('Encuesta.docx', self::docx(
            ['# Encuesta de clima', '1. ¿Cómo te sientes en tu equipo?', '- Bien', '- Mal', '', '2. ¿Qué cambiarías?'],
            [['Área', 'Responsable'], ['Ventas', 'Ana']],
        ));

        self::assertSame('Encuesta.docx', $file->filename);
        self::assertSame("# Encuesta de clima\n1. ¿Cómo te sientes en tu equipo?\n- Bien\n- Mal\n\n2. ¿Qué cambiarías?\nÁrea | Responsable\nVentas | Ana", $file->text, 'runs are joined; headings become "# ", list items "- ", table rows "a | b"');
    }

    public function testAPdfIsReadFromItsTextLayer(): void
    {
        $file = $this->files->read('preguntas.pdf', self::pdf(['Customer survey', '1. How did you hear about us?', '2. Would you recommend us?']));

        self::assertStringContainsString('Customer survey', $file->text);
        self::assertStringContainsString('1. How did you hear about us?', $file->text);
        self::assertStringContainsString('2. Would you recommend us?', $file->text);
    }

    public function testMarkdownAndTextFilesAreReadAsTheyAreAndAnotherEncodingIsConvertedToUtf8(): void
    {
        self::assertSame("# Título\n\n1. ¿Edad?", $this->files->read('q.md', "\xEF\xBB\xBF# Título\r\n\r\n\r\n\r\n1.   ¿Edad?  ")->text, 'BOM dropped, line breaks unified, blank lines and spaces collapsed');
        self::assertSame('¿Qué opinas?', $this->files->read('q.txt', "\xBFQu\xE9 opinas?")->text, 'Windows-1252 is converted to UTF-8');
    }

    public function testOnlyWordPdfMarkdownTextAndCsvFilesAreRead(): void
    {
        foreach (['notes.doc' => 'save the document as .docx', 'image.png' => null, 'sheet.xlsx' => null, 'noextension' => null] as $name => $hint) {
            try {
                $this->files->read($name, 'x');
                self::fail("$name must be refused");
            } catch (UnreadableFile $e) {
                self::assertSame('UNSUPPORTED_FILE_TYPE', $e->errorCode(), $name);
                if (null !== $hint) {
                    self::assertStringContainsStringIgnoringCase($hint, $e->getMessage(), 'a legacy .doc gets a hint');
                }
            }
        }
    }

    public function testAFileOverTenMegabytesIsRefusedBeforeItIsRead(): void
    {
        $this->expectRefusal('FILE_TOO_LARGE', fn () => $this->files->read('big.txt', str_repeat('a', AttachedFile::MAX_BYTES + 1)));
    }

    public function testAFileWithoutTextLikeAScannedPdfIsRefused(): void
    {
        $this->expectRefusal('FILE_HAS_NO_TEXT', fn () => $this->files->read('scan.pdf', self::pdf([])));
        $this->expectRefusal('FILE_HAS_NO_TEXT', fn () => $this->files->read('blank.md', "  \n\n "));
    }

    public function testAFileWithMoreThan100000CharactersOfTextIsRefused(): void
    {
        $this->expectRefusal('FILE_TOO_LONG', fn () => $this->files->read('long.txt', str_repeat('palabra ', 12_501)));
    }

    public function testADamagedOrMisnamedFileIsUnreadable(): void
    {
        $this->expectRefusal('FILE_UNREADABLE', fn () => $this->files->read('fake.docx', 'this is not a zip'));
        $this->expectRefusal('FILE_UNREADABLE', fn () => $this->files->read('fake.pdf', 'this is not a pdf'));
    }

    public function testTheFilesTheClientSendsBackAreCappedAndAnythingUnexpectedIsDropped(): void
    {
        $files = AttachedFile::listFromArray([
            ['filename' => '../../etc/passwd.md', 'text' => 'uno'],
            ['filename' => 'x.md', 'text' => '   '],
            'junk',
            ['filename' => 'a.md'],
            ['filename' => 'b.md', 'text' => 'dos'],
            ['filename' => 'c.md', 'text' => 'tres'],
            ['filename' => 'd.md', 'text' => 'cuatro'],
        ]);

        self::assertSame([['filename' => 'passwd.md', 'text' => 'uno'], ['filename' => 'b.md', 'text' => 'dos']], array_map(static fn (AttachedFile $f): array => $f->toArray(), $files), 'at most 5 entries are read; empty and malformed ones dropped; the name has no path');
    }

    private function expectRefusal(string $code, callable $read): void
    {
        try {
            $read();
            self::fail("Expected $code");
        } catch (UnreadableFile $e) {
            self::assertSame($code, $e->errorCode());
        }
    }
}
