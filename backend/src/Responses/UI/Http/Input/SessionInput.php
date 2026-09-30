<?php

namespace App\Responses\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The full session the respondent app sends to save or submit it (PRD §8.4 PUT/POST /questionnaire/session). Only
 * session_id, the values inside questions and user_data are read; the other fields of the session are accepted and
 * ignored (the server keeps its own copy).
 */
final class SessionInput
{
    public const EMAIL_PATTERN = '/^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/';

    #[Assert\NotNull]
    #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM], strict: false)]
    public ?string $session_id = null;

    /** @var array<int|string, mixed>|null */
    #[Assert\NotNull]
    public ?array $questions = null;

    /** @var array<string, mixed>|null {name?, email?, phone?} captured at the end (PRD §9.6) */
    #[Assert\Collection(
        fields: [
            'name' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 200)]),
            'email' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 254), new Assert\Regex(pattern: self::EMAIL_PATTERN, message: 'This value is not a valid email address.')]),
            'phone' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 50)]),
        ],
        allowExtraFields: true,
    )]
    public ?array $user_data = null;
}
