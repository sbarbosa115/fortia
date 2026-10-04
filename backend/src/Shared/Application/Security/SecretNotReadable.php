<?php

namespace App\Shared\Application\Security;

/** A sealed secret that cannot be opened: SETTINGS_ENCRYPTION_KEY changed, or the stored value was altered. */
final class SecretNotReadable extends \RuntimeException
{
}
