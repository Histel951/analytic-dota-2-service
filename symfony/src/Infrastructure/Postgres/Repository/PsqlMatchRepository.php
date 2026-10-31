<?php

namespace App\Infrastructure\Postgres\Repository;

use App\Domain\Match\Entity\MatchEntity;
use App\Domain\Match\Repository\MatchRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use App\Shared\Domain\MatchId;
use App\Shared\Domain\Region\Region;
use App\Shared\Domain\TeamId;

final readonly class PsqlMatchRepository implements MatchRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    private const TABLE = 'matches';

    /**
     * @throws Exception
     */
    public function save(MatchEntity $entity): void
    {
        $sql = "INSERT INTO " . self::TABLE . " 
                (match_id, start_time, duration, radiant_win, region) 
                VALUES (?, ?, ?, ?, ?) 
                ON CONFLICT (match_id) DO UPDATE SET 
                    start_time = EXCLUDED.start_time,
                    duration = EXCLUDED.duration,
                    radiant_win = EXCLUDED.radiant_win,
                    region = EXCLUDED.region";
        
        $this->connection->executeStatement(
            $sql,
            [
                $entity->getMatchId()->getValue(),
                $entity->getStartTime()->format('Y-m-d H:i:s'),
                $entity->getDuration(),
                $entity->isRadiantWin(),
                $entity->getRegion()?->getId(),
            ],
            [
                Types::BIGINT,
                Types::STRING,
                Types::INTEGER,
                Types::BOOLEAN,
                Types::INTEGER,
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function findByTeamIdAndDateRange(
        TeamId $teamId,
        ?DateTimeImmutable $dateFrom = null,
        ?DateTimeImmutable $dateTo = null
    ): array {
        $conditions = [];
        $params = [];
        $types = [];

        if ($dateFrom !== null) {
            $conditions[] = 'start_time >= ?';
            $params[] = $dateFrom->format('Y-m-d H:i:s');
            $types[] = Types::STRING;
        }

        if ($dateTo !== null) {
            $conditions[] = 'start_time <= ?';
            $params[] = $dateTo->format('Y-m-d H:i:s');
            $types[] = Types::STRING;
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT match_id, start_time, duration, radiant_win, region 
                FROM " . self::TABLE . " 
                $where 
                ORDER BY start_time DESC";

        $result = $this->connection->executeQuery($sql, $params, $types);
        $rows = $result->fetchAllAssociative();

        $entities = [];
        foreach ($rows as $row) {
            $entities[] = new MatchEntity(
                matchId: new MatchId((int)$row['match_id']),
                startTime: new DateTimeImmutable($row['start_time']),
                duration: (int)$row['duration'],
                radiantWin: (bool)$row['radiant_win'],
                region: $this->getRegionFromRow($row),
            );
        }

        return $entities;
    }

    /**
     * @throws Exception
     */
    public function findByMatchId(MatchId $matchId): ?MatchEntity
    {
        $sql = "SELECT match_id, start_time, duration, radiant_win, region 
                FROM " . self::TABLE . " 
                WHERE match_id = ?";

        $result = $this->connection->executeQuery(
            $sql,
            [$matchId->getValue()],
            [Types::BIGINT]
        );

        $row = $result->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return new MatchEntity(
            matchId: new MatchId((int)$row['match_id']),
            startTime: new DateTimeImmutable($row['start_time']),
            duration: (int)$row['duration'],
            radiantWin: (bool)$row['radiant_win'],
            region: $this->getRegionFromRow($row),
        );
    }

    private function getRegionFromRow(array $row): ?Region
    {
        $regionId = $row['region'] !== null ? (int)$row['region'] : null;

        return $regionId ? Region::fromId($regionId) : null;
    }
}