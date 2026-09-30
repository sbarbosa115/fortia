<?php

namespace App\Questionnaires\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class FlowNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('FLOW_NOT_FOUND', 'The flow does not exist.');
    }
}
