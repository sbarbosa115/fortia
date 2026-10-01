<?php

namespace App\Billing\UI\Http\Controller;

use App\Billing\Application\Command\ChangePlan;
use App\Billing\Application\Command\ManageSubscription;
use App\Billing\Application\Command\OpenBillingPortal;
use App\Billing\Application\Command\StartCheckout;
use App\Billing\UI\Http\Input\CheckoutInput;
use App\Billing\UI\Http\Output\CheckoutSessionOutput;
use App\Billing\UI\Http\Output\PlanChangeOutput;
use App\Billing\UI\Http\Output\PortalSessionOutput;
use App\Billing\UI\Http\Output\SubscriptionCancelOutput;
use App\Billing\UI\Http\Output\SubscriptionRenewalOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Checkout and the subscription (PRD §7.4, §8.3). No plan gate (§7.1). Changing billing needs write permission
 * (§4.2 "Edit billing and workspace": read-only members cannot); errors of the gateway are 502 STRIPE_UNAVAILABLE.
 */
#[OA\Tag(name: 'Billing')]
final class CheckoutController
{
    public function __construct(private readonly CommandBus $commands)
    {
    }

    #[Route('/checkout/session', name: 'api_checkout_session', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The hosted checkout page', content: new Model(type: CheckoutSessionOutput::class))]
    #[OA\Response(response: 400, description: 'PLAN_NOT_PURCHASABLE')]
    #[OA\Response(response: 404, description: 'PLAN_NOT_FOUND')]
    #[OA\Response(response: 502, description: 'STRIPE_UNAVAILABLE')]
    public function session(Caller $caller, #[Payload] CheckoutInput $input): JsonResponse
    {
        $this->mayChangeBilling($caller);
        $url = (string) $this->commands->dispatch(new StartCheckout($caller->customerId, $caller->email, (string) $input->plan_id, (string) $input->billing_interval));

        return ApiResponse::ok(new CheckoutSessionOutput($url));
    }

    #[Route('/checkout/plan-change', name: 'api_checkout_plan_change', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Changed now (upgrade) or at the end of the period (downgrade), or a checkout', content: new Model(type: PlanChangeOutput::class))]
    #[OA\Response(response: 400, description: 'SAME_PLAN, PLAN_NOT_PURCHASABLE')]
    #[OA\Response(response: 404, description: 'PLAN_NOT_FOUND')]
    #[OA\Response(response: 502, description: 'STRIPE_UNAVAILABLE')]
    public function planChange(Caller $caller, #[Payload] CheckoutInput $input): JsonResponse
    {
        $this->mayChangeBilling($caller);
        /** @var array<string, mixed> $result */
        $result = $this->commands->dispatch(new ChangePlan($caller->customerId, $caller->email, (string) $input->plan_id, (string) $input->billing_interval));

        return ApiResponse::ok(PlanChangeOutput::of($result));
    }

    #[Route('/checkout/plan-change/revert', name: 'api_checkout_plan_change_revert', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The scheduled change is undone', content: new Model(type: SubscriptionRenewalOutput::class))]
    #[OA\Response(response: 400, description: 'NO_SUBSCRIPTION, NO_SCHEDULED_CHANGE')]
    public function revert(Caller $caller): JsonResponse
    {
        $result = $this->manage($caller, ManageSubscription::REVERT);

        return ApiResponse::ok(new SubscriptionRenewalOutput($result['subscription_id'], $result['plan_id'], $result['renews_at'] ?? null));
    }

    #[Route('/checkout/cancel', name: 'api_checkout_cancel', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Canceled at the end of the period', content: new Model(type: SubscriptionCancelOutput::class))]
    #[OA\Response(response: 400, description: 'NO_SUBSCRIPTION')]
    public function cancel(Caller $caller): JsonResponse
    {
        $result = $this->manage($caller, ManageSubscription::CANCEL);

        return ApiResponse::ok(new SubscriptionCancelOutput($result['subscription_id'], $result['plan_id'], $result['active_until'] ?? null));
    }

    #[Route('/checkout/resume', name: 'api_checkout_resume', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The cancellation is removed (idempotent)', content: new Model(type: SubscriptionRenewalOutput::class))]
    #[OA\Response(response: 400, description: 'NO_SUBSCRIPTION')]
    #[OA\Response(response: 502, description: 'The period has already expired')]
    public function resume(Caller $caller): JsonResponse
    {
        $result = $this->manage($caller, ManageSubscription::RESUME);

        return ApiResponse::ok(new SubscriptionRenewalOutput($result['subscription_id'], $result['plan_id'], $result['renews_at'] ?? null));
    }

    #[Route('/checkout/portal', name: 'api_checkout_portal', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The gateway\'s billing portal', content: new Model(type: PortalSessionOutput::class))]
    #[OA\Response(response: 400, description: 'NO_STRIPE_CUSTOMER')]
    public function portal(Caller $caller): JsonResponse
    {
        $this->mayChangeBilling($caller);

        return ApiResponse::ok(new PortalSessionOutput((string) $this->commands->dispatch(new OpenBillingPortal($caller->customerId))));
    }

    /** @return array{subscription_id: string, plan_id: string|null, renews_at?: string|null, active_until?: string|null} */
    private function manage(Caller $caller, string $action): array
    {
        $this->mayChangeBilling($caller);

        return $this->commands->dispatch(new ManageSubscription($caller->customerId, $action));
    }

    private function mayChangeBilling(Caller $caller): void
    {
        if (!$caller->canWrite()) {
            throw new NotAllowed('FORBIDDEN', 'Your read-only role can\'t change billing.');
        }
    }
}
