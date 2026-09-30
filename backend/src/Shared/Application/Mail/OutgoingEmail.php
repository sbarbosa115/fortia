<?php

namespace App\Shared\Application\Mail;

final class OutgoingEmail
{
    /**
     * @param list<string>         $to
     * @param string               $template e.g. "emails/assignations/reminder.html.twig"
     * @param array<string, mixed> $context  variables for the template
     * @param 'es'|'en'            $locale
     * @param list<string>         $bcc
     */
    public function __construct(
        public readonly array $to,
        public readonly string $subject,
        public readonly string $template,
        public readonly array $context = [],
        public readonly string $locale = 'es',
        public readonly array $bcc = [],
    ) {
    }

    /** The account language (es-CO / en-US) as the email locale; es by default (PRD §7.13). */
    public static function localeOf(?string $accountLanguage): string
    {
        return null !== $accountLanguage && str_starts_with($accountLanguage, 'en') ? 'en' : 'es';
    }
}
