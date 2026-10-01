<?php

namespace App\Tests\Unit\Chat;

use App\Chat\Domain\Confirmation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** PRD §7.19: writes run, basics are confirmed and drafts approved only on the user's own explicit yes. */
final class ConfirmationTest extends TestCase
{
    /** @return iterable<array{string}> */
    public static function yeses(): iterable
    {
        yield ['Sí'];
        yield ['si'];
        yield ['SÍ!'];
        yield ['Yes'];
        yield ['ok, adelante'];
        yield ['Dale'];
        yield ['Yes, create it'];
        yield ['Confirmo 👍'];
        yield ['de acuerdo'];
    }

    /** @return iterable<array{string}> */
    public static function nos(): iterable
    {
        yield ['No'];
        yield ['no, gracias'];
        yield ['Cancelar'];
        yield ['Mejor no'];
        yield ['nope'];
    }

    /** @return iterable<array{string}> */
    public static function others(): iterable
    {
        yield ['Sí, pero cambia el título'];
        yield ['yes but shorter'];
        yield ['Crea un cuestionario sobre ventas'];
        yield ['sigue'];
        yield ['Sinceramente prefiero otro tema'];
        yield [''];
        yield ['Sí '.str_repeat('muy ', 20).'bien'];
    }

    #[DataProvider('yeses')]
    public function testAnExplicitYesConfirms(string $message): void
    {
        self::assertSame(Confirmation::Yes, Confirmation::of($message), "\"$message\" is a yes");
    }

    #[DataProvider('nos')]
    public function testAnExplicitNoDeclines(string $message): void
    {
        self::assertSame(Confirmation::No, Confirmation::of($message), "\"$message\" is a no");
    }

    #[DataProvider('others')]
    public function testAnythingElseNeitherConfirmsNorDeclines(string $message): void
    {
        self::assertSame(Confirmation::Other, Confirmation::of($message), "\"$message\" is not an explicit answer");
    }
}
