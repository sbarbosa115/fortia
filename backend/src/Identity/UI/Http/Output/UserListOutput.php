<?php

namespace App\Identity\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** PRD §8.2 GET /users: root first, then by name. */
final class UserListOutput
{
    /** @param list<UserOutput> $users */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: UserOutput::class)))]
        public readonly array $users,
    ) {
    }
}
