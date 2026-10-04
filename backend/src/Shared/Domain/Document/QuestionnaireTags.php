<?php

namespace App\Shared\Domain\Document;

use App\Shared\Domain\Error\Rejected;

/**
 * A questionnaire's tags: free-text labels the owner gives it ("AP-03", "NP-12", "Onboarding") to find it again. The
 * console editor, POST/PUT /questionnaire and the chat's draft all go through these rules:
 *
 * - every tag is trimmed (inner runs of spaces collapse to one) and an empty one is dropped;
 * - a repeated tag is dropped, comparing without case ("ap-03" after "AP-03" is the same tag): the first spelling stays;
 * - at most 20 tags of at most 40 characters each; more, or a longer one, is refused (400 VALIDATION_ERROR).
 */
final class QuestionnaireTags
{
    public const MAX_TAGS = 20;
    public const MAX_LENGTH = 40;

    /**
     * @return list<string>
     *
     * @throws Rejected VALIDATION_ERROR when it is not a list of texts, has too many tags or a tag is too long
     */
    public static function normalize(mixed $raw): array
    {
        if (null === $raw) {
            return [];
        }
        if (!\is_array($raw) || !array_is_list($raw)) {
            throw self::invalid('The tags must be a list of texts.');
        }
        $tags = [];
        $seen = [];
        foreach ($raw as $tag) {
            if (!\is_string($tag)) {
                throw self::invalid('Every tag must be a text.');
            }
            $tag = trim((string) preg_replace('/\s+/u', ' ', $tag));
            if ('' === $tag) {
                continue;
            }
            if (mb_strlen($tag) > self::MAX_LENGTH) {
                throw self::invalid(\sprintf('A tag has at most %d characters ("%s…" is longer).', self::MAX_LENGTH, mb_substr($tag, 0, 20)));
            }
            $key = mb_strtolower($tag);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $tags[] = $tag;
        }
        if (\count($tags) > self::MAX_TAGS) {
            throw self::invalid(\sprintf('A questionnaire has at most %d tags.', self::MAX_TAGS));
        }

        return $tags;
    }

    /**
     * The tags as stored, read back leniently (a row from before tags existed, or a JSON that is not a list, has none).
     *
     * @return list<string>
     */
    public static function fromStored(mixed $raw): array
    {
        if (\is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!\is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, 'is_string'));
    }

    private static function invalid(string $message): Rejected
    {
        return new Rejected('VALIDATION_ERROR', 'tags: '.$message, ['violations' => [['field' => 'tags', 'message' => $message]]]);
    }
}
