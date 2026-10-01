<?php

namespace App\Identity\UI\Http\Output;

use OpenApi\Attributes as OA;

/** The root user of a new account (PRD §8.2 POST /register). */
final class RegisteredUserOutput
{
    public function __construct(
        public readonly string $email,
        public readonly string $name,
        public readonly bool $root,
        #[OA\Property(enum: ['Customer-Admin'])]
        public readonly string $role,
    ) {
    }
}
