<?php

namespace App\Shared\UI\Http\Request;

use App\Shared\Domain\Error\Rejected;
use App\Shared\Domain\Ids;

/** Path parameters that must be a UUIDv4: 400 INVALID_UUID otherwise (PRD §8.1). */
final class RouteId
{
    public static function uuid(string $id): string
    {
        if (!Ids::isUuid4($id)) {
            throw new Rejected('INVALID_UUID', 'The id is not a valid UUID.');
        }

        return strtolower($id);
    }
}
