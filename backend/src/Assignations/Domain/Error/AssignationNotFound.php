<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/** 404: no such assignation, or it belongs to another account. */
final class AssignationNotFound extends NotFound
{
    public function __construct(?string $assignationsId = null)
    {
        parent::__construct('ASSIGNATION_NOT_FOUND', 'Assignation not found.', null === $assignationsId ? [] : ['assignation_id' => $assignationsId]);
    }
}
