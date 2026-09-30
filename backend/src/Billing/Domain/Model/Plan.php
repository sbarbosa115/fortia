<?php

declare(strict_types=1);

namespace App\Billing\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A plan of the global catalog (PRD §6.4). A feature limit < 0 is unlimited and 0 is "not included";
 * max_questionnaires / max_responses: null = no cap, negative = unlimited.
 */
#[ORM\Entity]
#[ORM\Table(name: 'plan')]
#[ORM\UniqueConstraint(name: 'uniq_plan_name', columns: ['plan_name'])]
class Plan
{
    public const STARTER = 'starter';

    #[ORM\Column(length: 100)]
    private string $planName;

    #[ORM\Column(type: Types::TEXT)]
    private string $planDescription = '';

    /** @var list<array{feature_id: string, limit: int}> */
    #[ORM\Column(type: Types::JSON)]
    private array $features = [];

    #[ORM\Column(nullable: true)]
    private ?int $maxQuestionnaires = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxResponses = null;

    #[ORM\Column(nullable: true)]
    private ?int $priceAmount = null;

    #[ORM\Column(length: 3)]
    private string $currency = 'usd';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePriceId = null;

    #[ORM\Column(nullable: true)]
    private ?int $yearlyPriceAmount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeYearlyPriceId = null;

    #[ORM\Column]
    private int $trialDays = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array{
     *     plan_name: string,
     *     plan_description?: string,
     *     features?: list<array{feature_id: string, limit: int}>,
     *     max_questionnaires?: ?int,
     *     max_responses?: ?int,
     *     price_amount?: ?int,
     *     currency?: string,
     *     stripe_price_id?: ?string,
     *     yearly_price_amount?: ?int,
     *     stripe_yearly_price_id?: ?string,
     *     trial_days?: int,
     * } $fields
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 100)]
        private string $id,
        array $fields,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->planName = $fields['plan_name'];
        $this->replace($fields, $createdAt);
    }

    /**
     * Full replacement (PUT /admin/plans/{id}); the id does not change.
     *
     * @param array<string, mixed> $fields the fields of PRD §6.4
     */
    public function replace(array $fields, \DateTimeImmutable $at): void
    {
        $this->planName = (string) ($fields['plan_name'] ?? $this->planName);
        $this->planDescription = (string) ($fields['plan_description'] ?? '');
        $features = [];
        foreach ((array) ($fields['features'] ?? []) as $feature) {
            if (\is_array($feature) && isset($feature['feature_id'])) {
                $features[] = ['feature_id' => (string) $feature['feature_id'], 'limit' => (int) ($feature['limit'] ?? 0)];
            }
        }
        $this->features = $features;
        $this->maxQuestionnaires = isset($fields['max_questionnaires']) ? (int) $fields['max_questionnaires'] : null;
        $this->maxResponses = isset($fields['max_responses']) ? (int) $fields['max_responses'] : null;
        $this->priceAmount = isset($fields['price_amount']) ? (int) $fields['price_amount'] : null;
        $this->currency = strtolower((string) ($fields['currency'] ?? 'usd'));
        $this->stripePriceId = isset($fields['stripe_price_id']) ? (string) $fields['stripe_price_id'] : null;
        $this->yearlyPriceAmount = isset($fields['yearly_price_amount']) ? (int) $fields['yearly_price_amount'] : null;
        $this->stripeYearlyPriceId = isset($fields['stripe_yearly_price_id']) ? (string) $fields['stripe_yearly_price_id'] : null;
        $this->trialDays = (int) ($fields['trial_days'] ?? 0);
        $this->updatedAt = $at;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function planName(): string
    {
        return $this->planName;
    }

    public function planDescription(): string
    {
        return $this->planDescription;
    }

    /** @return list<array{feature_id: string, limit: int}> */
    public function features(): array
    {
        return $this->features;
    }

    /** The limit of a feature, or null when the plan does not list it. */
    public function limitOf(string $featureId): ?int
    {
        foreach ($this->features as $feature) {
            if ($feature['feature_id'] === $featureId) {
                return $feature['limit'];
            }
        }

        return null;
    }

    public function maxQuestionnaires(): ?int
    {
        return $this->maxQuestionnaires;
    }

    public function maxResponses(): ?int
    {
        return $this->maxResponses;
    }

    public function priceAmount(): ?int
    {
        return $this->priceAmount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function stripePriceId(): ?string
    {
        return $this->stripePriceId;
    }

    public function yearlyPriceAmount(): ?int
    {
        return $this->yearlyPriceAmount;
    }

    public function stripeYearlyPriceId(): ?string
    {
        return $this->stripeYearlyPriceId;
    }

    public function trialDays(): int
    {
        return $this->trialDays;
    }

    /** Can be bought in the gateway monthly: it has a price and a gateway price id. */
    public function isPurchasable(): bool
    {
        return null !== $this->priceAmount && null !== $this->stripePriceId;
    }

    public function isYearlyPurchasable(): bool
    {
        return null !== $this->yearlyPriceAmount && null !== $this->stripeYearlyPriceId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
