<?php

namespace App\Integrations\Infrastructure\Persistence;

use App\Integrations\Application\Port\ExternalCatalog;
use App\Shared\Domain\Iso;
use Doctrine\DBAL\Connection;

/**
 * The external API's listings straight from the questionnaire, flow and questionnaire_session tables, one page per
 * query plus a count (D17), so this context names no class of Questionnaires or Responses. Indexes used:
 * idx_questionnaire_customer (customer_id, parent, created_at) and idx_session_questionnaire (questionnaire_id,
 * started_at).
 */
final class DbalExternalCatalog implements ExternalCatalog
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function questionnaires(string $customerId, int $page, int $pageSize): array
    {
        $total = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM questionnaire WHERE customer_id = ? AND parent = 'ROOT'",
            [$customerId],
        );
        $rows = $this->connection->createQueryBuilder()
            ->select('q.questionnaire_id, f.id AS flow_id, q.slug, f.slug AS flow_slug, q.title, q.description, q.is_active, q.type, q.created_at, q.updated_at')
            ->from('questionnaire', 'q')
            ->leftJoin('q', 'flow', 'f', 'f.questionnaire_id = q.questionnaire_id')
            ->where('q.customer_id = :customer')
            ->andWhere("q.parent = 'ROOT'")
            ->setParameter('customer', $customerId)
            ->orderBy('q.created_at', 'DESC')
            ->addOrderBy('q.questionnaire_id', 'DESC')
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->executeQuery()
            ->fetchAllAssociative();

        return ['items' => array_map(static fn (array $row): array => [
            'id' => (string) $row['questionnaire_id'],
            'flow_id' => null === $row['flow_id'] ? null : (string) $row['flow_id'],
            'slug' => $row['slug'] ?? $row['flow_slug'],
            'title' => (string) $row['title'],
            'description' => $row['description'],
            'is_active' => (bool) $row['is_active'],
            'type' => (string) $row['type'],
            'created_at' => self::iso($row['created_at']),
            'updated_at' => self::iso($row['updated_at']),
        ], $rows), 'total' => $total];
    }

    public function sessionIds(string $questionnaireId, int $page, int $pageSize): array
    {
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM questionnaire_session WHERE questionnaire_id = ?', [$questionnaireId]);
        /** @var list<string> $ids */
        $ids = $this->connection->createQueryBuilder()
            ->select('s.session_id')
            ->from('questionnaire_session', 's')
            ->where('s.questionnaire_id = :questionnaire')
            ->setParameter('questionnaire', $questionnaireId)
            ->orderBy('s.started_at', 'DESC')
            ->addOrderBy('s.session_id', 'DESC')
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->executeQuery()
            ->fetchFirstColumn();

        return ['ids' => array_map('strval', $ids), 'total' => $total];
    }

    private static function iso(mixed $value): ?string
    {
        return null === $value ? null : Iso::datetime(new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC')));
    }
}
