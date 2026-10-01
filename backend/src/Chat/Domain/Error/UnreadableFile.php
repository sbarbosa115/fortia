<?php

namespace App\Chat\Domain\Error;

use App\Shared\Domain\Error\InvalidValue;

/**
 * 422: a file attached to the chat that cannot be used — UNSUPPORTED_FILE_TYPE, FILE_TOO_LARGE, FILE_TOO_LONG,
 * FILE_HAS_NO_TEXT or FILE_UNREADABLE (see AttachedFile).
 */
final class UnreadableFile extends InvalidValue
{
}
