<?php
declare(strict_types=1);

namespace App\Statistics\Domain\Port;

use App\Statistics\Domain\PlayerStats\PlayerMetric;

interface PlayerStatsReadRepository
{
    /**
     * @return PlayerMetric[]
     */
    public function findTopPlayersByMetric(string $metricColumn, int $limit, int $offset): array;
}
