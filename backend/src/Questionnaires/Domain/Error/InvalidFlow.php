<?php

namespace App\Questionnaires\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/**
 * A flow or its questionnaire breaks a rule of PRD §7.5 (flow validation, a scorable diagnostic, the slug, CTA,
 * layout and result copy shapes). Answered like any validation error: 400 VALIDATION_ERROR with the messages
 * flattened as "field: message; …" and details.violations.
 */
final class InvalidFlow extends Rejected
{
    /** @param list<array{field: string, message: string}> $violations */
    public function __construct(public readonly array $violations)
    {
        $flat = implode('; ', array_map(
            static fn (array $v): string => '' === $v['field'] ? $v['message'] : $v['field'].': '.$v['message'],
            $violations,
        ));
        parent::__construct('VALIDATION_ERROR', $flat, ['violations' => $violations]);
    }

    /** @param list<array{field: string, message: string}> $violations */
    public static function unless(array $violations): void
    {
        if ([] !== $violations) {
            throw new self($violations);
        }
    }
}
