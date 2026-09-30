<?php

namespace App\Organizations\Infrastructure\Persistence;

use App\Organizations\Application\Port\OrganizationDependents;
use Doctrine\DBAL\Connection;

/**
 * Counts the assignations and projects of an organization straight from their tables (both indexed by
 * organization_id), so this context names no class of the Assignations context. Built before Assignations has a
 * query for it; it can move to an AssignationQueries method once that context adds one.
 */
final class DbalOrganizationDependents implements OrganizationDependents
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function count(string $organizationId): array
    {
        $assignations = $this->connection->fetchOne('SELECT COUNT(*) FROM assignation WHERE organization_id = ?', [$organizationId]);
        $projects = $this->connection->fetchOne('SELECT COUNT(*) FROM project WHERE organization_id = ?', [$organizationId]);

        return ['assignations' => (int) $assignations, 'projects' => (int) $projects];
    }
}
