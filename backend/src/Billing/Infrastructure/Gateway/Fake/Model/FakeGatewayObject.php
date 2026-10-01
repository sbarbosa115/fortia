<?php

namespace App\Billing\Infrastructure\Gateway\Fake\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The fake payment gateway's state, as the gateway would keep it: customers, checkout sessions, subscriptions,
 * portal sessions, prices, coupons and promotion codes, each a JSON document in the gateway's own shape.
 *
 * Mapped only so the schema (and its migration) owns the table; FakePaymentGateway reads and writes it with DBAL,
 * outside our unit of work, like an external system that does not roll back with us.
 */
#[ORM\Entity]
#[ORM\Table(name: 'fake_gateway_object')]
#[ORM\Index(name: 'idx_fake_gateway_object_kind', columns: ['kind', 'created_at'])]
class FakeGatewayObject
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 64)]
        private string $id,
        #[ORM\Column(length: 32)]
        private string $kind,
        /** @var array<string, mixed> */
        #[ORM\Column(type: Types::JSON)]
        private array $data,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
