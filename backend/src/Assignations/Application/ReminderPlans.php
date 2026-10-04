<?php

namespace App\Assignations\Application;

use App\Assignations\Application\Port\AssignationMailer;
use App\Assignations\Application\Query\FollowUpStatus;
use App\Assignations\Domain\Audience;
use App\Assignations\Domain\Error\FollowUpCompleted;
use App\Assignations\Domain\Error\NoRecipients;
use App\Assignations\Domain\Error\NotAFollowUp;
use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\ReminderTiming;
use App\Shared\Application\Mail\MailNotSent;
use App\Shared\Domain\Clock;

/**
 * Whether a follow-up can be reminded and to whom (PRD §7.13): a follow-up, not completed, with at least one
 * respondent email (the audience's, deduplicated). The account's root users get the status unless they are
 * respondents themselves.
 */
final class ReminderPlans
{
    public function __construct(
        private readonly FollowUpStatus $followUps,
        private readonly AssignationMailContext $context,
        private readonly AssignationMailer $mailer,
        private readonly Clock $clock,
    ) {
    }

    /** @throws NotAFollowUp|FollowUpCompleted|NoRecipients */
    public function of(Assignation $assignation): ReminderPlan
    {
        if (!$assignation->isFollowUp()) {
            throw new NotAFollowUp();
        }
        $progress = $this->followUps->of($assignation);
        if ($progress->ended) {
            throw new FollowUpCompleted();
        }
        $recipients = Audience::emails($assignation->audience(), $this->context->members($assignation));
        if ([] === $recipients) {
            throw new NoRecipients();
        }
        $view = $this->context->view($assignation);

        return new ReminderPlan(
            $assignation,
            $view,
            $this->context->locale($assignation),
            ReminderTiming::of($view['due_date'], $this->clock->today()),
            $recipients,
            // An owner who is also a respondent only receives the reminder (§7.13).
            array_values(array_diff($this->context->rootEmails($assignation), $recipients)),
            ['completed' => $progress->completed, 'total' => $progress->total, 'percent' => (int) round($progress->percent())],
        );
    }

    /**
     * The status email to the root users, saying $reminded people were reminded.
     *
     * @throws MailNotSent
     */
    public function sendStatus(ReminderPlan $plan, int $reminded): void
    {
        if ([] !== $plan->roots) {
            $this->mailer->sendStatus($plan->view, $plan->roots, $plan->locale, $plan->timing, $plan->progress, $reminded);
        }
    }
}
