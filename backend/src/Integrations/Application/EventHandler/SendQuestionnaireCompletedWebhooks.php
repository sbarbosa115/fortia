<?php

namespace App\Integrations\Application\EventHandler;

use App\Billing\Application\Features;
use App\Billing\Application\PlanGate;
use App\Integrations\Application\WebhookDeliverer;
use App\Integrations\Application\WebhookPayload;
use App\Integrations\Domain\Model\WebhookDelivery;
use App\Integrations\Domain\Repository\WebhookDeliveryRepository;
use App\Integrations\Domain\Repository\WebhookRepository;
use App\Responses\Application\Query\SessionQueries;
use App\Responses\Domain\Event\QuestionnaireSessionCompleted;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A completed session publishes questionnaire.completed to the account's webhooks (PRD §7.7 step 3, §7.14), on the
 * worker. The "webhook" capacity is checked first: when the plan rejects it nothing is delivered (and nothing is
 * logged). Otherwise each subscribed URL gets a delivery in the log (D19) and its first attempt right away; failed
 * attempts are retried by RetryDueWebhooks.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class SendQuestionnaireCompletedWebhooks
{
    public function __construct(
        private readonly PlanGate $gate,
        private readonly WebhookRepository $webhooks,
        private readonly WebhookDeliveryRepository $deliveries,
        private readonly SessionQueries $sessions,
        private readonly WebhookDeliverer $deliverer,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(QuestionnaireSessionCompleted $event): void
    {
        $customerId = $event->customerId();
        if (null === $customerId || !$this->gate->allows($customerId, Features::WEBHOOK)) {
            return;
        }
        $subscriptions = $this->webhooks->subscribedTo($customerId, WebhookPayload::QUESTIONNAIRE_COMPLETED);
        if ([] === $subscriptions) {
            return;
        }
        $answers = $this->sessions->answersOf($event->sessionId());
        if (null === $answers) {
            return;
        }

        $payload = WebhookPayload::questionnaireCompleted($customerId, $event->questionnaireId(), $event->sessionId(), $answers);
        foreach ($subscriptions as $webhook) {
            $delivery = new WebhookDelivery(Ids::uuid4(), $webhook->id(), $customerId, WebhookPayload::QUESTIONNAIRE_COMPLETED, $payload, $this->clock->now());
            $this->deliveries->add($delivery);
            $this->deliverer->attempt($delivery, $webhook);
        }
    }
}
