<?php

namespace App\Organizations\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** GET /organizations (PRD §8.7), bare: {organizations: [{...org, organization_users: [...]}]}. */
final class OrganizationListOutput
{
    /**
     * @param list<OrganizationOutput> $organizations
     */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: OrganizationOutput::class)))]
        public readonly array $organizations,
    ) {
    }
}
