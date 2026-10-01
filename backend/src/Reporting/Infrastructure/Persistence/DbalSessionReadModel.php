<?php

namespace App\Reporting\Infrastructure\Persistence;

use App\Reporting\Application\Port\SessionReadModel;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Reporting is a read-side context: it reads the sessions and results tables with plain SQL (no entity of the
 * Responses context), paginated in the database on the (questionnaire_id, started_at) index. Rows are mapped to
 * the SessionQueries::sessionData() shape: the questionnaire copy merged with the session columns.
 */
final class DbalSessionReadModel implements SessionReadModel
{
    private const COLUMNS = 'session_id, questionnaire_id, customer_id, document, started_at, ended_at, flow_id, status, user_data, assignations_id, organization_user_id, assignation_type, attempt';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function page(string $questionnaireId, ?array $statuses, ?string $assignationsId, int $offset, int $limit): array
    {
        [$where, $params, $types] = self::filter($questionnaireId, $statuses, $assignationsId);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM questionnaire_session WHERE '.$where
            .' ORDER BY started_at DESC, session_id ASC LIMIT '.max(1, $limit).' OFFSET '.max(0, $offset),
            $params,
            $types,
        );

        return array_map(self::session(...), $rows);
    }

    public function count(string $questionnaireId, ?array $statuses, ?string $assignationsId): int
    {
        [$where, $params, $types] = self::filter($questionnaireId, $statuses, $assignationsId);

        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM questionnaire_session WHERE '.$where, $params, $types);
    }

    public function all(string $questionnaireId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM questionnaire_session WHERE questionnaire_id = ? ORDER BY started_at ASC, session_id ASC',
            [$questionnaireId],
        );

        return array_map(self::session(...), $rows);
    }

    public function diagnostics(string $questionnaireId): array
    {
        $out = [];
        foreach ($this->connection->fetchFirstColumn('SELECT diagnostic FROM session_results WHERE questionnaire_id = ? AND diagnostic IS NOT NULL', [$questionnaireId]) as $json) {
            $diagnostic = json_decode((string) $json, true);
            if (\is_array($diagnostic)) {
                $out[] = $diagnostic;
            }
        }

        return $out;
    }

    /**
     * @param list<string>|null $statuses
     *
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, ArrayParameterType>}
     */
    private static function filter(string $questionnaireId, ?array $statuses, ?string $assignationsId): array
    {
        $where = 'questionnaire_id = :questionnaire';
        $params = ['questionnaire' => $questionnaireId];
        $types = [];
        if (null !== $statuses) {
            $where .= ' AND status IN (:statuses)';
            $params['statuses'] = $statuses;
            $types['statuses'] = ArrayParameterType::STRING;
        }
        if (null !== $assignationsId) {
            $where .= ' AND assignations_id = :assignation';
            $params['assignation'] = $assignationsId;
        }

        return [$where, $params, $types];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function session(array $row): array
    {
        $document = json_decode((string) $row['document'], true);
        $document = \is_array($document) ? $document : [];
        $userData = null === $row['user_data'] ? null : json_decode((string) $row['user_data'], true);
        $questions = $document['questions'] ?? [];

        return array_merge($document, [
            'session_id' => (string) $row['session_id'],
            'questionnaire_id' => (string) $row['questionnaire_id'],
            'customer_id' => (string) $row['customer_id'],
            'started_at' => self::iso($row['started_at']),
            'ended_at' => self::iso($row['ended_at']),
            'flow_id' => $row['flow_id'],
            'status' => (string) $row['status'],
            'user_data' => \is_array($userData) ? $userData : null,
            'assignations_id' => $row['assignations_id'],
            'organization_user_id' => $row['organization_user_id'],
            'assignation_type' => $row['assignation_type'],
            'attempt' => (int) $row['attempt'],
            'questions' => \is_array($questions) ? array_values(array_filter($questions, 'is_array')) : [],
        ]);
    }

    /** The database keeps UTC wall-clock times ("Y-m-d H:i:s"); the API writes ISO-8601 with Z. */
    private static function iso(mixed $value): ?string
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
