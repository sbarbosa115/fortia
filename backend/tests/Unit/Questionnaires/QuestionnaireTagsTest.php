<?php

namespace App\Tests\Unit\Questionnaires;

use App\Shared\Domain\Document\QuestionnaireTags;
use App\Shared\Domain\Error\Rejected;
use PHPUnit\Framework\TestCase;

/** A questionnaire's tags: free-text labels, trimmed, deduplicated without case, at most 20 of at most 40 characters. */
final class QuestionnaireTagsTest extends TestCase
{
    public function testTagsAreTrimmedAndEmptiesDropped(): void
    {
        self::assertSame(['AP-03', 'Two words'], QuestionnaireTags::normalize(['  AP-03 ', '', '   ', "Two \t  words"]), 'trimmed, inner spaces collapsed, empties dropped');
    }

    public function testARepeatedTagIsDroppedWhateverItsCaseKeepingTheFirstSpelling(): void
    {
        self::assertSame(['AP-03', 'Árbol'], QuestionnaireTags::normalize(['AP-03', 'ap-03', 'Árbol', 'ÁRBOL', ' Ap-03 ']));
    }

    public function testNoTagsIsAnEmptyList(): void
    {
        self::assertSame([], QuestionnaireTags::normalize(null));
        self::assertSame([], QuestionnaireTags::normalize([]));
    }

    public function testAtMostTwentyTagsAfterRepeatsAreDropped(): void
    {
        $twenty = array_map(static fn (int $i): string => 'T'.$i, range(1, 20));

        self::assertCount(20, QuestionnaireTags::normalize([...$twenty, 't1', 'T2']), 'repeats do not count');
        $this->assertRefused(static fn () => QuestionnaireTags::normalize([...$twenty, 'T21']), 'at most 20 tags');
    }

    public function testATagHasAtMostFortyCharacters(): void
    {
        self::assertSame([str_repeat('ñ', 40)], QuestionnaireTags::normalize([str_repeat('ñ', 40)]), 'characters, not bytes');
        $this->assertRefused(static fn () => QuestionnaireTags::normalize([str_repeat('a', 41)]), 'at most 40 characters');
    }

    public function testTagsMustBeAListOfTexts(): void
    {
        $this->assertRefused(static fn () => QuestionnaireTags::normalize('AP-03'), 'a list');
        $this->assertRefused(static fn () => QuestionnaireTags::normalize(['a' => 'AP-03']), 'a list, not an object');
        $this->assertRefused(static fn () => QuestionnaireTags::normalize([12]), 'texts only');
    }

    public function testStoredTagsAreReadBackLeniently(): void
    {
        self::assertSame(['AP-03'], QuestionnaireTags::fromStored('["AP-03"]'));
        self::assertSame([], QuestionnaireTags::fromStored(null), 'a row from before tags existed has none');
        self::assertSame(['A'], QuestionnaireTags::fromStored(['A', 3]));
    }

    private function assertRefused(callable $action, string $why): void
    {
        try {
            $action();
            self::fail('Expected VALIDATION_ERROR: '.$why);
        } catch (Rejected $e) {
            self::assertSame('VALIDATION_ERROR', $e->errorCode(), $why);
            self::assertStringStartsWith('tags: ', $e->getMessage());
        }
    }
}
