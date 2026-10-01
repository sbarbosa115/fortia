<?php

namespace App\Chat\Application\Tool;

use App\Billing\Application\Command\ChangePlan;
use App\Billing\Application\Command\ManageSubscription;
use App\Billing\Application\Command\OpenBillingPortal;
use App\Billing\Application\Command\StartCheckout;
use App\Billing\Application\Query\PlanQueries;
use App\Billing\Application\Query\UsageQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;

/**
 * PRD §7.19 "Plan and billing", with the checks of PRD §8.3 (every billing change needs write permission; checkout
 * has no plan gate). start_checkout and open_billing_portal only make a link the user opens (nothing changes until
 * they act on the payment page), so they run at once; changing, reverting, cancelling and resuming are queued.
 */
final class BillingTools implements ChatToolbox
{
    public function __construct(
        private readonly UsageQueries $usage,
        private readonly PlanQueries $plans,
        private readonly CommandBus $commands,
    ) {
    }

    public function tools(): array
    {
        $plan = [
            'plan_id' => Schema::string('The plan\'s id, from list_plans.', 100),
            'billing_interval' => Schema::enum(['month', 'year'], 'Monthly (default) or yearly billing.'),
        ];

        return [
            ChatTool::read(
                'get_plan_and_usage',
                'The account\'s plan, its period, and how much of each feature it used this period.',
                [],
                [],
                fn (Caller $caller): array => $this->usage->forAccount($caller->customerId),
            ),
            ChatTool::read(
                'list_plans',
                'The plans the account can buy, its current plan and any scheduled change.',
                [],
                [],
                fn (Caller $caller): array => $this->plans->forAccount($caller->customerId),
            ),
            ChatTool::read(
                'start_checkout',
                'Makes the payment page link for a plan; show it to the user as a link. Nothing is charged until they pay there.',
                $plan,
                ['plan_id'],
                function (Caller $caller, ToolInput $input): array {
                    Permissions::write($caller);

                    return ['url' => (string) $this->commands->dispatch(new StartCheckout($caller->customerId, $caller->email, $input->string('plan_id', 100), self::interval($input)))];
                },
            ),
            ChatTool::read(
                'open_billing_portal',
                'Makes the link to the billing portal (invoices, payment method); show it to the user as a link.',
                [],
                [],
                function (Caller $caller): array {
                    Permissions::write($caller);

                    return ['url' => (string) $this->commands->dispatch(new OpenBillingPortal($caller->customerId))];
                },
            ),
            ChatTool::write(
                'change_plan',
                'Changes the plan: an upgrade applies now, a downgrade at the end of the period; without a subscription it returns a payment link.',
                $plan,
                ['plan_id'],
                static function (Caller $caller, ToolInput $input): string {
                    Permissions::write($caller);
                    self::interval($input);

                    return $input->string('plan_id', 100);
                },
                function (Caller $caller, ToolInput $input): array {
                    /** @var array<string, mixed> $result */
                    $result = $this->commands->dispatch(new ChangePlan($caller->customerId, $caller->email, $input->string('plan_id', 100), self::interval($input)));

                    return $result;
                },
            ),
            $this->manage('revert_plan_change', 'Cancels a scheduled downgrade: the current plan renews.', ManageSubscription::REVERT),
            $this->manage('cancel_subscription', 'Cancels the subscription at the end of the paid period.', ManageSubscription::CANCEL),
            $this->manage('resume_subscription', 'Undoes a cancellation: the subscription renews again.', ManageSubscription::RESUME),
        ];
    }

    private function manage(string $name, string $description, string $action): ChatTool
    {
        return ChatTool::write(
            $name,
            $description,
            [],
            [],
            static function (Caller $caller): string {
                Permissions::write($caller);

                return '';
            },
            function (Caller $caller) use ($action): array {
                /** @var array<string, mixed> $result */
                $result = $this->commands->dispatch(new ManageSubscription($caller->customerId, $action));

                return $result;
            },
        );
    }

    private static function interval(ToolInput $input): string
    {
        return $input->choice('billing_interval', ['month', 'year'], 'month');
    }
}
