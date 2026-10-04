<?php

namespace App\Assignations\Infrastructure\Mail;

use App\Assignations\Application\Port\AssignationMailer;
use App\Shared\Application\Mail\Mailer;
use App\Shared\Application\Mail\OutgoingEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The assignation emails as Twig templates (templates/emails/assignations/*.html.twig) with their texts and subjects
 * in translations/emails_assignations+intl-icu.{es,en}.yaml. One message per recipient, so respondents never see
 * each other's address. Links: the respondent's {FRONTEND_URL}/a/{id}, the console's {ADMIN_URL}/assignations/{id}.
 */
final class TwigAssignationMailer implements AssignationMailer
{
    private const DOMAIN = 'emails_assignations';

    public function __construct(
        private readonly Mailer $mailer,
        private readonly TranslatorInterface $translator,
        private readonly string $frontendUrl,
        private readonly string $adminFrontendUrl,
    ) {
    }

    public function sendReminder(array $assignation, array $to, string $locale, array $timing): void
    {
        $subject = $this->translator->trans('reminder.subject.'.$timing['kind'], ['name' => $assignation['name'], 'days' => $timing['days']], self::DOMAIN, $locale);
        $this->each($to, $subject, 'reminder', $locale, [
            'assignation' => $assignation,
            'timing' => $timing,
            'link' => $this->respondentLink($assignation),
        ]);
    }

    public function sendReminderDigest(string $to, array $pending, string $locale): void
    {
        $items = [];
        foreach ($pending as $reminder) {
            /** @var array{due_date?: string|null} $assignation */
            $assignation = $reminder['assignation'];
            $items[] = [
                'assignation' => $reminder['assignation'],
                'timing' => $reminder['timing'],
                'due_date' => self::longDate($assignation['due_date'] ?? null, $locale),
                'link' => $this->respondentLink($reminder['assignation']),
            ];
        }
        $count = \count($items);
        $subject = $this->translator->trans('reminder_digest.subject', ['count' => $count], self::DOMAIN, $locale);
        $customerId = $pending[0]['assignation']['customer_id'] ?? null;
        $this->mailer->send(new OutgoingEmail([$to], $subject, 'emails/assignations/reminder_digest.html.twig', ['count' => $count, 'items' => $items], $locale, [], \is_string($customerId) ? $customerId : null));
    }

    public function sendStatus(array $assignation, array $to, string $locale, array $timing, array $progress, int $reminded): void
    {
        $subject = $this->translator->trans('status.subject', ['name' => $assignation['name'], 'percent' => $progress['percent']], self::DOMAIN, $locale);
        $this->each($to, $subject, 'status', $locale, [
            'assignation' => $assignation,
            'timing' => $timing,
            'progress' => $progress,
            'reminded' => $reminded,
            'link' => rtrim($this->adminFrontendUrl, '/').'/assignations/'.$assignation['assignations_id'],
        ]);
    }

    public function sendRetry(array $assignation, array $to, string $locale, int $attempt, int $rejected): void
    {
        $subject = $this->translator->trans('retry.subject', ['name' => $assignation['name']], self::DOMAIN, $locale);
        $this->each($to, $subject, 'retry', $locale, [
            'assignation' => $assignation,
            'attempt' => $attempt,
            'rejected' => $rejected,
            'link' => $this->respondentLink($assignation),
        ]);
    }

    /**
     * @param list<string>         $to
     * @param array<string, mixed> $context
     */
    private function each(array $to, string $subject, string $template, string $locale, array $context): void
    {
        /** @var array{due_date?: string|null, customer_id?: string} $assignation */
        $assignation = $context['assignation'];
        $context['due_date'] = self::longDate($assignation['due_date'] ?? null, $locale);
        foreach ($to as $address) {
            $this->mailer->send(new OutgoingEmail([$address], $subject, 'emails/assignations/'.$template.'.html.twig', $context, $locale, [], $assignation['customer_id'] ?? null));
        }
    }

    /** "2026-10-03" → "3 de octubre de 2026" / "October 3, 2026". */
    private static function longDate(?string $date, string $locale): string
    {
        if (null === $date || '' === $date) {
            return '';
        }
        $formatter = new \IntlDateFormatter('es' === $locale ? 'es_CO' : 'en_US', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, 'UTC');
        $formatted = $formatter->format(new \DateTimeImmutable($date.'T00:00:00Z'));

        return false === $formatted ? $date : $formatted;
    }

    /** @param array<string, mixed> $assignation */
    private function respondentLink(array $assignation): string
    {
        return rtrim($this->frontendUrl, '/').'/a/'.$assignation['assignations_id'];
    }
}
