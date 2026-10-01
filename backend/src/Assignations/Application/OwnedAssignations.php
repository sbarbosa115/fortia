<?php

namespace App\Assignations\Application;

use App\Assignations\Domain\Error\AssignationNotFound;
use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Shared\Application\Security\Caller;

/** Loads an assignation of the caller's account (any account for an Admin): another account's is 404. */
final class OwnedAssignations
{
    public function __construct(private readonly AssignationRepository $assignations)
    {
    }

    public function get(Caller $caller, string $assignationsId): Assignation
    {
        $assignation = $this->assignations->find($assignationsId);
        if (null === $assignation || !$caller->owns($assignation->customerId())) {
            throw new AssignationNotFound($assignationsId);
        }

        return $assignation;
    }
}
