<?php

namespace App\Billing\Application\Port;

use App\Shared\Application\Mail\MailNotSent;

/**
 * Sends a sales lead to the sales team (PRD §7.21 "Sales lead", in Spanish, to SALES_LEAD_RECIPIENTS — D8, texts in
 * translations — D23).
 */
interface SalesLeadNotifier
{
    /**
     * @param array{customer_id: string, user_name: string, user_email: string, plan_id: string|null,
     *              plan_name: string|null, contact_email: string, phone: string, type: string} $lead
     *
     * @throws MailNotSent
     */
    public function send(array $lead): void;
}
