<?php

namespace App\Commerce\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** The store's website could not be read (PRD §10.5: "Could not access URL. Please ensure it is a public store."). */
final class CatalogUnreachable extends Rejected
{
    public function __construct(string $url)
    {
        parent::__construct('CATALOG_UNREACHABLE', \sprintf('Could not access %s. Please ensure it is a public store.', $url));
    }
}
