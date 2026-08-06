<?php
declare(strict_types=1);

namespace App\Application\PlayerStats\Service;

use App\Application\PlayerStats\Dto\TopFantasyPlayer;
use App\Domain\Match\Repository\FantasyStatsReadRepository;

final readonly class GetTopFantasyPlayersService
{
    private const METRIC_COLUMN_TO_NAME = [
        'kills_score' => 'kills',
        'gpm_score' => 'gpm',
        'roshan_score' => 'roshan_kills',
        'tower_score' => 'tower_kills',
        'observer_score' => 'observers_placed',
        'stacks_score' => 'camps_stacked',
        'rune_score' => 'rune_pickups',
        'courier_score' => 'courier_kills',
        'stun_score' => 'stuns',
        'first_blood_score' => 'first_blood_claimed',
        'teamfight_score' => 'teamfight_participation',
        'tormentor_score' => 'tormentor_kills',
        'smoke_score' => 'smoke_uses',
        'watcher_score' => 'watcher',
    ];

    public function __construct(
        private FantasyStatsReadRepository $repository,
    ) {}

    /**
     * @return TopFantasyPlayer[]
     */
    public function getTopFantasyPlayers(int $limit, int $matches, int $offset = 0): array
    {
        $results = $this->repository->findTopFantasyPlayers($limit, $matches, $offset);

        return array_map(
            fn(array $row) => new TopFantasyPlayer(
                playerId: $row['player_id'],
                matches: $row['matches'],
                averageTotalScore: $row['average_total_score'],
                topMetrics: $this->selectTopMetrics($row),
            ),
            $results
        );
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getFantasyStatsByMatchId(int $matchId, int $limit = 100, int $offset = 0): array
    {
        return $this->repository->findFantasyStatsByMatchId($matchId, $limit, $offset);
    }

    private function selectTopMetrics(array $row): array
    {
        $metrics = [];

        foreach (self::METRIC_COLUMN_TO_NAME as $column => $metricName) {
            if (!array_key_exists($column, $row)) {
                continue;
            }

            $metrics[$metricName] = (float) $row[$column];
        }

        arsort($metrics, SORT_NUMERIC);

        return array_slice($metrics, 0, 3, true);
    }
}
