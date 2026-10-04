<?php

namespace App\Questionnaires\Infrastructure\Persistence;

use App\Questionnaires\Application\Query\ListingCriteria;
use App\Questionnaires\Application\Query\QuestionnaireListing;
use App\Shared\Domain\Document\QuestionnaireTags;
use App\Shared\Domain\Iso;
use App\Shared\Domain\Text;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * GET /questionnaire in SQL: one query for the page (never the questions document) and one for the total. The
 * listing "type" filter is the kind a row shows as: its on_completed type, else its own type (quiz funnel and
 * diagnostic), else "default". Search: every word in the title, with LIKE escaped; the table's utf8mb4_0900_ai_ci
 * collation ignores case and accents (PRD §8.1). Tag: one of the row's tags, whole, also without case or accents.
 */
final class DbalQuestionnaireListing implements QuestionnaireListing
{
    private const COLUMNS = 'q.questionnaire_id, q.customer_id, q.parent, q.origin_session_id, q.title, q.description, q.created_at, q.updated_at, q.is_active, q.on_completed, q.landing_page, q.capture_user_data, q.question_count, q.is_chain, q.slug, q.type, q.tags';
    private const TAGS = "JSON_TABLE(q.tags, '$[*]' COLUMNS(tag VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci PATH '$')) AS t";
    private const KIND = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(q.on_completed, '$.type')), CASE WHEN q.type = 'diagnostic' THEN 'diagnostic' WHEN q.type IN ('quiz_funnel', 'ecommerce') THEN 'quiz_funnel' ELSE 'default' END)";

    public function __construct(private readonly Connection $connection)
    {
    }

    public function page(ListingCriteria $criteria): array
    {
        $total = (int) $this->filtered($criteria)->select('COUNT(*)')->executeQuery()->fetchOne();

        $rows = $this->filtered($criteria)
            ->select(self::COLUMNS)
            ->orderBy('q.'.$criteria->sortBy, 'asc' === $criteria->order ? 'ASC' : 'DESC')
            ->addOrderBy('q.questionnaire_id', 'asc' === $criteria->order ? 'ASC' : 'DESC')
            ->setFirstResult($criteria->offset())
            ->setMaxResults($criteria->pageSize)
            ->executeQuery()
            ->fetchAllAssociative();

        return ['items' => array_map(self::item(...), $rows), 'total' => $total];
    }

    public function row(string $questionnaireId): ?array
    {
        $row = $this->connection->createQueryBuilder()
            ->select(self::COLUMNS)
            ->from('questionnaire', 'q')
            ->where('q.questionnaire_id = :id')
            ->setParameter('id', $questionnaireId)
            ->executeQuery()
            ->fetchAssociative();

        return false === $row ? null : self::item($row);
    }

    public function tags(?string $customerId): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('t.tag')
            ->from('questionnaire q, '.self::TAGS)
            ->where("q.parent = 'ROOT'")
            ->orderBy('q.created_at')
            ->addOrderBy('q.questionnaire_id');
        if (null !== $customerId) {
            $qb->andWhere('q.customer_id = :customer')->setParameter('customer', $customerId);
        }
        $tags = [];
        foreach ($qb->executeQuery()->fetchFirstColumn() as $tag) {
            $tags[mb_strtolower((string) $tag)] ??= (string) $tag;
        }
        ksort($tags, \SORT_NATURAL);

        return array_values($tags);
    }

    private function filtered(ListingCriteria $criteria): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder()
            ->from('questionnaire', 'q')
            ->where('q.parent = :parent')
            ->setParameter('parent', $criteria->parent);
        if (null !== $criteria->customerId) {
            $qb->andWhere('q.customer_id = :customer')->setParameter('customer', $criteria->customerId);
        }
        if (null !== $criteria->type) {
            $qb->andWhere(self::KIND.' = :kind')->setParameter('kind', $criteria->type);
        }
        if (null !== $criteria->isActive) {
            $qb->andWhere('q.is_active = :active')->setParameter('active', $criteria->isActive ? 1 : 0);
        }
        if (null !== $criteria->tag) {
            $qb->andWhere('EXISTS (SELECT 1 FROM '.self::TAGS.' WHERE t.tag = :tag)')->setParameter('tag', $criteria->tag);
        }
        foreach ($criteria->searchWords as $i => $word) {
            $qb->andWhere("q.title LIKE :word$i")->setParameter("word$i", '%'.Text::escapeLike($word).'%');
        }

        return $qb;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function item(array $row): array
    {
        $onCompleted = \is_string($row['on_completed']) ? json_decode($row['on_completed'], true) : null;
        $active = (bool) $row['is_active'];

        return [
            'questionnaire_id' => (string) $row['questionnaire_id'],
            'customer_id' => (string) $row['customer_id'],
            'parent' => (string) $row['parent'],
            'origin_session_id' => null === $row['origin_session_id'] ? null : (string) $row['origin_session_id'],
            'title' => (string) $row['title'],
            'description' => null === $row['description'] ? null : (string) $row['description'],
            'created_at' => self::datetime($row['created_at']),
            'updated_at' => self::datetime($row['updated_at']),
            'is_active' => $active,
            'on_completed' => \is_array($onCompleted) ? $onCompleted : null,
            'status' => $active ? 'active' : 'inactive',
            'landing_page' => (bool) $row['landing_page'],
            'capture_user_data' => (bool) $row['capture_user_data'],
            'question_count' => (int) $row['question_count'],
            'is_chain' => (bool) $row['is_chain'],
            'slug' => null === $row['slug'] ? null : (string) $row['slug'],
            'type' => (string) $row['type'],
            'tags' => QuestionnaireTags::fromStored($row['tags']),
        ];
    }

    private static function datetime(mixed $value): ?string
    {
        return \is_string($value) ? Iso::datetime(new \DateTimeImmutable($value, new \DateTimeZone('UTC'))) : null;
    }
}
