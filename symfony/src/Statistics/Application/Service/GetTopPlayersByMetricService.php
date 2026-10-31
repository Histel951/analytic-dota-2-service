<?php
declare(strict_types=1);

namespace App\Statistics\Application\Service;

use App\Statistics\Application\Dto\TopPlayerByMetric;
use App\Statistics\Application\Exception\InvalidPaginationException;
use App\Statistics\Application\Exception\UnsupportedMetricException;
use App\Statistics\Domain\Port\PlayerStatsReadRepository;

final readonly class GetTopPlayersByMetricService
{
    private const int DEFAULT_LIMIT = 50;
    private const int MAX_LIMIT = 200;
    private const int DEFAULT_OFFSET = 0;

    private const array SUPPORTED_METRICS = [
        'gpm' => 'gpm',
        'xpm' => 'xpm',
        'net_worth' => 'net_worth',
        'total_gold' => 'total_gold',
        'total_xp' => 'total_xp',
        'kills' => 'kills',
        'deaths' => 'deaths',
        'assists' => 'assists',
        'hero_damage' => 'hero_damage',
        'tower_damage' => 'tower_damage',
        'hero_healing' => 'hero_healing',
        'creeps_last_hits' => 'creeps_last_hits',
        'creeps_denies' => 'creeps_denies',
        'observers_placed' => 'observers_placed',
        'teamfight_participation' => 'teamfight_participation',
    ];

    public function __construct(
        private PlayerStatsReadRepository $repository,
    ) {}

    /**
     * @return TopPlayerByMetric[]
     */
    public function getTopPlayers(string $metric, ?int $limit = null, ?int $offset = null): array
    {
        $validatedMetric = $this->validateMetric($metric);
        $validatedLimit = $this->validateLimit($limit ?? self::DEFAULT_LIMIT);
        $validatedOffset = $this->validateOffset($offset ?? self::DEFAULT_OFFSET);

        $results = $this->repository->findTopPlayersByMetric(
            $validatedMetric,
            $validatedLimit,
            $validatedOffset
        );

        return array_map(
            fn($result) => new TopPlayerByMetric(
                playerId: $result->getPlayerId()->getValue(),
                metric: $metric,
                value: $result->getMetricValue(),
                matchesCount: $result->getMatchesCount(),
            ),
            $results
        );
    }

    private function validateMetric(string $metric): string
    {
        if (!isset(self::SUPPORTED_METRICS[$metric])) {
            throw new UnsupportedMetricException($metric);
        }

        return self::SUPPORTED_METRICS[$metric];
    }

    private function validateLimit(int $limit): int
    {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw InvalidPaginationException::invalidLimit($limit, self::MAX_LIMIT);
        }

        return $limit;
    }

    private function validateOffset(int $offset): int
    {
        if ($offset < 0) {
            throw InvalidPaginationException::invalidOffset($offset);
        }

        return $offset;
    }
}
