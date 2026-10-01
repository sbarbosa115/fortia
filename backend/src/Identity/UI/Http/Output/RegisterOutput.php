<?php

namespace App\Identity\UI\Http\Output;

/** PRD §8.2 POST /register: the new account and its root user. No tokens: the client signs in next. */
final class RegisterOutput
{
    public function __construct(
        public readonly string $customer_id,
        public readonly RegisteredUserOutput $user,
    ) {
    }

    /** @param array{customer_id: string, email: string, name: string} $row */
    public static function of(array $row): self
    {
        return new self($row['customer_id'], new RegisteredUserOutput($row['email'], $row['name'], true, 'Customer-Admin'));
    }
}
