<?php

namespace App\Billing\Application\Port;

/** A webhook whose signature (or body) does not verify: answered 401 in plain text (PRD §8.3). */
final class InvalidWebhookSignature extends \RuntimeException
{
}
