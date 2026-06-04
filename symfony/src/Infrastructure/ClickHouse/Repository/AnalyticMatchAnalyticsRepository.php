<?php
declare(strict_types=1);

namespace App\Infrastructure\ClickHouse\Repository;

use App\Domain\Match\Entity\PlayerMatchStats;
use App\Domain\Match\Repository\AnalyticMatchRepository;
use ClickHouseDB\Client;

final readonly class AnalyticMatchAnalyticsRepository implements AnalyticMatchRepository
{
    public function __construct(
        private Client $clickhouse,
    ) {}

    public function insert(array $rows): void
    {
        $this->clickhouse->insert(
            'player_match_stats',
            $rows,
            [
                'match_id',
                'player_id',
                'hero_id',
                'kills',
                'deaths',
                'assists',
                'gpm',
                'xpm',
            ]
        );
    }

    public function insertMatchStats(PlayerMatchStats $match): void
    {
        $this->clickhouse->insert(
            'player_match_stats',
            [],
            [

            ]
        );
    }
}