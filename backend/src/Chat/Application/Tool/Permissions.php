<?php

namespace App\Chat\Application\Tool;

use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;

/**
 * The role checks of the HTTP endpoints, for the tools that do what those endpoints do (PRD §4.2, §8): "AG" (Admin or
 * Customer-Admin) and the console's write permission (root, Admin or Customer-Admin). Same codes, same messages.
 */
final class Permissions
{
    public static function adminGroups(Caller $caller): void
    {
        if (!$caller->inAdminGroups()) {
            throw new NotAllowed('FORBIDDEN', 'Admin privileges are required.');
        }
    }

    public static function write(Caller $caller): void
    {
        if (!$caller->canWrite()) {
            throw new NotAllowed('FORBIDDEN', "Your read-only role can't make changes.");
        }
    }
}
