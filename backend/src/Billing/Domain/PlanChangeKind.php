<?php

namespace App\Billing\Domain;

/** What a plan change is (PRD §7.4): an upgrade takes effect now, a downgrade at the end of the period. */
enum PlanChangeKind: string
{
    case Upgrade = 'upgrade';
    case Downgrade = 'downgrade';
}
