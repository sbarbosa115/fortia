<?php

namespace App\Billing\Infrastructure\Mail;

use App\Billing\Application\Port\SalesLeadNotifier;
use App\Shared\Application\Mail\Mailer;
use App\Shared\Application\Mail\OutgoingEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The sales lead email (PRD §7.21): always in Spanish, subject "[Ventas] {name} está interesado en el plan {plan}",
 * to the SALES_LEAD_RECIPIENTS list (D8, comma-separated). Texts in translations/emails_billing+intl-icu.*.yaml (D23).
 */
final class MailSalesLeadNotifier implements SalesLeadNotifier
{
    private const LOCALE = 'es';

    /**
     * @param list<string> $recipients
     */
    public function __construct(
        private readonly Mailer $mailer,
        private readonly TranslatorInterface $translator,
        #[Autowire(env: 'csv:SALES_LEAD_RECIPIENTS')]
        private readonly array $recipients,
    ) {
    }

    public function send(array $lead): void
    {
        $to = array_values(array_filter(array_map('trim', $this->recipients), static fn (string $address): bool => '' !== $address));
        $subject = $this->translator->trans('sales_lead.subject', [
            'name' => $lead['user_name'],
            'plan' => $lead['plan_name'] ?? $lead['plan_id'] ?? '',
        ], 'emails_billing', self::LOCALE);

        $this->mailer->send(new OutgoingEmail($to, $subject, 'emails/billing/sales_lead.html.twig', ['lead' => $lead], self::LOCALE));
    }
}
