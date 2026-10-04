<?php

namespace App\Identity\Domain\Error;

use App\Shared\Domain\Error\InvalidValue;

/** The account's SMTP server is incomplete or malformed (422 INVALID_SMTP_SERVER, details.field names the field). */
final class InvalidSmtpServer extends InvalidValue
{
    public function __construct(string $field, string $message)
    {
        parent::__construct('INVALID_SMTP_SERVER', $message, ['field' => $field]);
    }
}
