<?php

namespace App\Generation\Domain;

/**
 * Text written by someone other than the platform (the account owner's prompt, a public profile) that goes into a
 * model request as data (PRD §7.8, §14: the customer's prompt is untrusted — defense against prompt injection). It is
 * placed between tags the system prompt names as data, so it cannot close those tags or open new ones of its own.
 */
final class UntrustedText
{
    public const MAX_CHARS = 20_000;

    /** The text with every tag-like "<name>" / "</name>" neutralized and its length capped. */
    public static function neutralize(string $text, int $maxChars = self::MAX_CHARS): string
    {
        $text = mb_substr(trim($text), 0, $maxChars);

        return (string) preg_replace('#<(/?)([A-Za-z_][\w:-]*)([^>]*)>#u', '‹$1$2$3›', $text);
    }

    /** The text wrapped in <$tag>…</$tag>, neutralized. */
    public static function wrap(string $tag, string $text, int $maxChars = self::MAX_CHARS): string
    {
        return "<$tag>\n".self::neutralize($text, $maxChars)."\n</$tag>";
    }
}
