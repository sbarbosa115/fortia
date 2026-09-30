<?php

namespace App\Shared\Domain\Document;

/** The answer field of a question (PRD §6.5 InputControl.type). */
enum ControlType: string
{
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Select = 'select';
    case Range = 'range';
    case Text = 'text';
    case Audio = 'audio';
    case Ranking = 'ranking';
    case File = 'file';
    case Message = 'message';
    case Email = 'email';
    case Tel = 'tel';
    case Phone = 'phone';

    public const VALUES = ['radio', 'checkbox', 'select', 'range', 'text', 'audio', 'ranking', 'file', 'message', 'email', 'tel', 'phone'];

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }

    /** Selection controls: their options carry the value that is scored and reported. */
    public function isSelection(): bool
    {
        return \in_array($this, [self::Radio, self::Checkbox, self::Select, self::Ranking], true);
    }

    /** Controls whose value is a list. */
    public function isMultiValued(): bool
    {
        return \in_array($this, [self::Checkbox, self::Ranking, self::Audio, self::File], true);
    }
}
