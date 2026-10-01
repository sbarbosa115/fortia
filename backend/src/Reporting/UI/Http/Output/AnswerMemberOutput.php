<?php

namespace App\Reporting\UI\Http\Output;

/** The organization member who answered an assignation's session. */
final class AnswerMemberOutput
{
    public function __construct(
        public readonly string $organization_user_id,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $phone,
    ) {
    }
}
