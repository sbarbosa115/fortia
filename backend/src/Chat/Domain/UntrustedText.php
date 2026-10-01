<?php

namespace App\Chat\Domain;

/**
 * Data that goes into the assistant's instructions but was not written by the platform: the draft and the queue the
 * client sends back, what the tools read from the account (PRD §14: defense against prompt injection). It is placed
 * between tags the rules name as data, and cannot close them or open tags of its own.
 */
final class UntrustedText
{
    /** The text with every tag-like "<name>" / "</name>" neutralized and its length capped. */
    public static function neutralize(string $text, int $maxChars = 60_000): string
    {
        $text = mb_substr(trim($text), 0, $maxChars);

        return (string) preg_replace('#<(/?)([A-Za-z_][\w:-]*)([^>]*)>#u', '‹$1$2$3›', $text);
    }

    /** The text wrapped in <$tag>…</$tag>, neutralized. */
    public static function wrap(string $tag, string $text, int $maxChars = 60_000): string
    {
        return "<$tag>\n".self::neutralize($text, $maxChars)."\n</$tag>";
    }

    /** JSON data wrapped in <$tag>. */
    public static function json(string $tag, mixed $data, int $maxChars = 60_000): string
    {
        return self::wrap($tag, (string) json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES), $maxChars);
    }
}
