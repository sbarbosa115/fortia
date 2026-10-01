<?php

namespace App\Chat\Domain;

use App\Shared\Domain\Text;

/**
 * What the user's last message says to a question the assistant asked (PRD §7.19): an explicit yes, an explicit no,
 * or something else. Writes only run, basics are only confirmed and a draft is only approved on an explicit yes, and
 * the server decides it from the user's own words — never the language model, so nothing the model reads (a
 * questionnaire title, a member's name) can confirm a write for the user.
 *
 * A yes is short ("Sí", "ok, adelante", "Yes, create it"); a message that says yes and asks for something else
 * ("sí, pero cambia el título") is not one.
 */
enum Confirmation: string
{
    case Yes = 'yes';
    case No = 'no';
    case Other = 'other';

    private const MAX_LENGTH = 60;
    private const YES = [
        'si', 'sip', 'yes', 'yep', 'yeah', 'y', 'ok', 'okay', 'vale', 'dale', 'claro', 'confirmo', 'confirmar', 'confirmado',
        'confirm', 'confirmed', 'adelante', 'hazlo', 'go ahead', 'do it', 'sure', 'de acuerdo', 'aprobar', 'apruebo',
        'aprobado', 'approve', 'approved', 'crear', 'crealo', 'create', 'create it', 'perfecto', 'perfect', 'correcto',
        'correct', 'listo', 'por supuesto', 'of course', 'go', 'proceed', 'continuar', 'procede',
    ];
    private const NO = [
        'no', 'nope', 'nah', 'cancel', 'cancelar', 'cancela', 'cancelalo', 'mejor no', 'not now', 'ahora no', 'negativo',
        'detente', 'stop', 'dont', 'do not', 'descartar', 'discard',
    ];
    private const BUT = ['pero', 'but', 'aunque', 'however', 'excepto', 'except', 'cambia', 'change'];

    public static function of(string $message): self
    {
        $text = Text::fold($message);
        $text = trim((string) preg_replace('/[^a-z0-9 ]+/', ' ', $text));
        $text = (string) preg_replace('/\s+/', ' ', $text);
        if ('' === $text || mb_strlen($text) > self::MAX_LENGTH) {
            return self::Other;
        }
        $words = explode(' ', $text);
        if ([] !== array_intersect($words, self::BUT)) {
            return self::Other;
        }
        if (self::startsWithAny($text, self::NO)) {
            return self::No;
        }

        return self::startsWithAny($text, self::YES) ? self::Yes : self::Other;
    }

    /** @param list<string> $phrases */
    private static function startsWithAny(string $text, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if ($text === $phrase || str_starts_with($text, $phrase.' ')) {
                return true;
            }
        }

        return false;
    }
}
