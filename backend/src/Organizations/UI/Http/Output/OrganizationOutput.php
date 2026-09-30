<?php

namespace App\Organizations\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** An organization with its members (PRD §6.12, §8.7). */
final class OrganizationOutput
{
    /**
     * @param list<OrganizationUserOutput> $organization_users
     */
    public function __construct(
        public readonly string $organization_id,
        public readonly string $customer_id,
        public readonly string $name,
        public readonly ?string $domain_email,
        public readonly ?string $description,
        public readonly bool $active,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: OrganizationUserOutput::class)))]
        public readonly array $organization_users,
    ) {
    }

    /** @param array<string, mixed> $data OrganizationQueries::organizationData() */
    public static function of(array $data): self
    {
        /** @var list<array<string, mixed>> $members */
        $members = $data['organization_users'] ?? [];

        return new self(
            (string) $data['organization_id'],
            (string) $data['customer_id'],
            (string) $data['name'],
            null === $data['domain_email'] ? null : (string) $data['domain_email'],
            null === $data['description'] ? null : (string) $data['description'],
            (bool) $data['active'],
            null === $data['created_at'] ? null : (string) $data['created_at'],
            null === $data['updated_at'] ? null : (string) $data['updated_at'],
            array_map(OrganizationUserOutput::of(...), $members),
        );
    }
}
