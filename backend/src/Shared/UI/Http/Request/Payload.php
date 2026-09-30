<?php

declare(strict_types=1);

namespace App\Shared\UI\Http\Request;

/**
 * Maps the JSON body onto an Input DTO and validates it (PayloadValueResolver):
 *
 *     public function __invoke(#[Payload(allowExtraFields: false)] CreateProjectInput $input): Response
 *
 * Input DTOs are plain classes with public typed properties and defaults (no constructor); required fields are
 * nullable with #[Assert\NotNull] so a missing one is a validation message, not a crash. Types are strict: "true"
 * is not a bool. Errors: 400 INVALID_JSON, 400 VALIDATION_ERROR ("field: message; …", details.violations).
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Payload
{
    public function __construct(
        /** PRD "No extra fields allowed": an unknown key is a validation error. */
        public readonly bool $allowExtraFields = true,
        /** Validation groups, when an Input DTO is shared by create and update. */
        public readonly ?array $groups = null,
    ) {
    }
}
