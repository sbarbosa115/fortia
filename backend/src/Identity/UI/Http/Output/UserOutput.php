<?php

namespace App\Identity\UI\Http\Output;

use OpenApi\Attributes as OA;

/** A console user of an account (PRD §8.2 GET/POST /users). */
final class UserOutput
{
    public function __construct(
        public readonly string $email,
        public readonly string $name,
        public readonly bool $root,
        #[OA\Property(enum: ['Admin', 'Customer-Admin', 'Customer-Read-Only'])]
        public readonly string $role,
        public readonly string $customer_id,
    ) {
    }

    /** @param array{email: string, name: string, root: bool, role: string, customer_id: string} $row */
    public static function of(array $row): self
    {
        return new self($row['email'], $row['name'], $row['root'], $row['role'], $row['customer_id']);
    }
}
