<?php

namespace App\Branding\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/**
 * An account's styles were saved by the styles job. Counts one "styles" (PRD §7.2: a completed styles job; a failed
 * one saves nothing and publishes nothing). Payload: {website, from_website}.
 */
final class BrandStylesUpdated extends BaseDomainEvent
{
    public const FEATURE = 'styles';

    public static function of(string $customerId, ?string $website, bool $fromWebsite): self
    {
        return new self($customerId, self::FEATURE, ['website' => $website, 'from_website' => $fromWebsite]);
    }
}
