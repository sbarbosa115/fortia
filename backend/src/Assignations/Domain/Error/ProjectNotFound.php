<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/** 404: no such project, or it belongs to another account (another tenant's id is 404, never 403). */
final class ProjectNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('PROJECT_NOT_FOUND', 'Project not found.');
    }
}
