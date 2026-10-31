<?php
declare(strict_types=1);

namespace App\Infrastructure\ClickHouse\Repository;

use App\Domain\PlayerStats\Entity\PlayerMetric;
use App\Domain\PlayerStats\Repository\PlayerStatsReadRepository;
use ClickHouseDB\Client;
use App\Shared\Domain\PlayerId;

final readonly class ClickHousePlayerStatsReadRepository implements PlayerStatsReadRepository
{
    public function __construct(
        private Client $clickhouse,
    ) {}

    public function findTopPlayersByMetric(string $metricColumn, int $limit, int $offset): array
    {
        $sql = sprintf(
            'SELECT 
                player_id,
                avg(%s) AS metric_value,
                count(*) AS matches_count
            FROM player_match_stats
            GROUP BY player_id
            ORDER BY metric_value DESC
            LIMIT %d OFFSET %d',
            $metricColumn,
            $limit,
            $offset
        );

        $statement = $this->clickhouse->select($sql);
        $rows = $statement->rows();

        return array_map(
            fn(array $row) => new PlayerMetric(
                playerId: new PlayerId((int) $row['player_id']),
                metricValue: (float) $row['metric_value'],
                matchesCount: (int) $row['matches_count'],
            ),
            $rows
        );
    }
}
