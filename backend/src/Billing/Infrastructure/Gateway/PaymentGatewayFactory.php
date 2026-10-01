<?php

namespace App\Billing\Infrastructure\Gateway;

use App\Billing\Application\Port\PaymentGateway;
use App\Billing\Infrastructure\Gateway\Fake\FakePaymentGateway;
use App\Billing\Infrastructure\Gateway\Stripe\StripePaymentGateway;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

/** PAYMENT_PROVIDER → the adapter: "stripe" (STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET) or "fake" (default). */
final class PaymentGatewayFactory
{
    /**
     * @param ServiceLocator<PaymentGateway> $adapters
     */
    public function __construct(
        #[AutowireLocator([FakePaymentGateway::class, StripePaymentGateway::class])]
        private readonly ServiceLocator $adapters,
    ) {
    }

    public function create(string $provider): PaymentGateway
    {
        return 'stripe' === $provider
            ? $this->adapters->get(StripePaymentGateway::class)
            : $this->adapters->get(FakePaymentGateway::class);
    }
}
