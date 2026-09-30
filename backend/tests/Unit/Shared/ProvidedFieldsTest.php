<?php

namespace App\Tests\Unit\Shared;

use App\Shared\UI\Http\Request\ProvidedFieldsTrait;
use App\Shared\UI\Http\Request\TracksProvidedFields;
use PHPUnit\Framework\TestCase;

final class ProvidedFieldsTest extends TestCase
{
    public function testTellsAFieldSentAsNullFromOneNotSent(): void
    {
        $input = new PartialUpdateInput();
        $input->markProvided(['due_date']);

        self::assertTrue($input->wasProvided('due_date'), 'PRD §8.8: "due_date: null clears it" needs to know it was sent');
        self::assertFalse($input->wasProvided('name'));
        self::assertSame(['due_date'], $input->providedFields());
    }
}

final class PartialUpdateInput implements TracksProvidedFields
{
    use ProvidedFieldsTrait;

    public ?string $due_date = null;
    public ?string $name = null;
}
