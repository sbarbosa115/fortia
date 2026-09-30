<?php

declare(strict_types=1);

namespace App\Shared\UI\Http\Request;

use App\Shared\Domain\Error\Rejected;

/** 400 VALIDATION_ERROR, with the messages flattened as "field: message; …" (PRD §8.1). */
final class ValidationFailed extends Rejected
{
    /** @param list<array{field: string, message: string}> $violations */
    public function __construct(array $violations)
    {
        $flat = implode('; ', array_map(
            static fn (array $v): string => '' === $v['field'] ? $v['message'] : $v['field'].': '.$v['message'],
            $violations,
        ));
        parent::__construct('VALIDATION_ERROR', $flat, ['violations' => $violations]);
    }

    public static function field(string $field, string $message): self
    {
        return new self([['field' => $field, 'message' => $message]]);
    }
}
