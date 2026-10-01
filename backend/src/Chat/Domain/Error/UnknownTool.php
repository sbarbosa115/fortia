<?php

namespace App\Chat\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** 400 UNKNOWN_TOOL: the model asked for a tool this mode does not offer. */
final class UnknownTool extends Rejected
{
    public function __construct(string $name)
    {
        parent::__construct('UNKNOWN_TOOL', \sprintf('There is no tool "%s" here.', mb_substr($name, 0, 80)));
    }
}
