<?php

namespace App\Billing\Application\Command;

use App\Billing\Application\Port\SalesLeadNotifier;
use App\Billing\Domain\Error\EmailUnavailable;
use App\Billing\Domain\Error\PlanNotFound;
use App\Billing\Domain\Repository\PlanRepository;
use App\Shared\Application\Mail\MailNotSent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RequestSalesContactHandler
{
    public function __construct(
        private readonly PlanRepository $plans,
        private readonly SalesLeadNotifier $notifier,
    ) {
    }

    public function __invoke(RequestSalesContact $command): void
    {
        $plan = null;
        if (null !== $command->planId) {
            $plan = $this->plans->find($command->planId) ?? throw new PlanNotFound();
        }
        try {
            $this->notifier->send([
                'customer_id' => $command->customerId,
                'user_name' => $command->userName,
                'user_email' => $command->userEmail,
                'plan_id' => $plan?->id(),
                'plan_name' => $plan?->planName(),
                'contact_email' => $command->email,
                'phone' => $command->phone,
                'type' => $command->type,
            ]);
        } catch (MailNotSent $e) {
            throw new EmailUnavailable($e);
        }
    }
}
