<?php

namespace App\Identity\UI\Http\Input;

use App\Shared\UI\Http\Request\ProvidedFieldsTrait;
use App\Shared\UI\Http\Request\TracksProvidedFields;

/**
 * POST /customer/{customer_id}/system-settings/smtp-check: the form's smtp_* fields over the saved server (an empty
 * body checks the saved server as it is).
 */
final class SmtpCheckInput implements TracksProvidedFields
{
    use ProvidedFieldsTrait;
    use SmtpServerFields;
}
