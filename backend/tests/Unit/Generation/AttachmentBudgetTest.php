<?php

namespace App\Tests\Unit\Generation;

use App\Generation\Domain\AttachmentBudget;
use PHPUnit\Framework\TestCase;

final class AttachmentBudgetTest extends TestCase
{
    public function testAtMostFiveFilesGoToTheModel(): void
    {
        $budget = new AttachmentBudget();
        $taken = 0;
        for ($i = 0; $i < 7; ++$i) {
            $taken += null === $budget->admit("notes-$i.txt", 'hello') ? 0 : 1;
        }

        self::assertSame(5, $taken, '§7.8: up to 5 files per stage');
        self::assertTrue($budget->isFull());
    }

    public function testEachTypeHasItsOwnLimit(): void
    {
        $budget = new AttachmentBudget();

        self::assertSame('application/pdf', $budget->admit('report.PDF', str_repeat('x', 1024)));
        self::assertNull($budget->admit('photo.png', str_repeat('x', AttachmentBudget::MAX_IMAGE_BYTES + 1)), 'an image is at most 20 MB');
        self::assertSame('image/jpeg', $budget->admit('photo.jpeg', str_repeat('x', 1024)));
        self::assertNull($budget->admit('big.txt', str_repeat('x', AttachmentBudget::MAX_TEXT_BYTES + 1)), 'a text file is at most 256 KB');
        self::assertNull($budget->admit('wide.md', str_repeat('é', 100_001)), 'a text file is at most 100,000 characters');
        self::assertSame('text/csv', $budget->admit('data.csv', "a,b\n1,2"));
        self::assertNull($budget->admit('macro.docx', 'x'), 'other types are not sent');
        self::assertNull($budget->admit('empty.txt', ''), 'an empty file says nothing');
    }

    public function testTheStageCarriesAtMost32MegabytesInTotal(): void
    {
        $budget = new AttachmentBudget();
        $twenty = str_repeat('x', 20 * 1024 * 1024);

        self::assertNotNull($budget->admit('a.pdf', $twenty));
        self::assertNull($budget->admit('b.pdf', $twenty), '§7.8: 32 MB in total per stage');
        self::assertNotNull($budget->admit('c.txt', 'still fits'));
    }
}
