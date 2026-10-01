<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** 400 VALIDATION_ERROR from the domain's own rules, in the same "field: message; …" shape as the Input DTOs'. */
final class InvalidAssignation extends Rejected
{
    /** @param list<array{field: string, message: string}> $violations */
    public function __construct(array $violations)
    {
        $flat = implode('; ', array_map(static fn (array $v): string => '' === $v['field'] ? $v['message'] : $v['field'].': '.$v['message'], $violations));
        parent::__construct('VALIDATION_ERROR', $flat, ['violations' => $violations]);
    }
}
