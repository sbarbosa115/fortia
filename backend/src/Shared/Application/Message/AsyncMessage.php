<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

/**
 * A message the worker handles, not the request (config/packages/messenger.yaml routes it to the "async"
 * transport): jobs, domain events and everything they trigger (emails, webhooks, usage counters). In tests the
 * transport is synchronous.
 */
interface AsyncMessage
{
}
