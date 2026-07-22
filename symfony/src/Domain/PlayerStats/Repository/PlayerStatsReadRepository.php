<?php
declare(strict_types=1);

namespace App\Domain\PlayerStats\Repository;

use App\Domain\PlayerStats\Entity\PlayerMetric;

interface PlayerStatsReadRepository
{
    /**
     * @return PlayerMetric[]
     */
    public function findTopPlayersByMetric(string $metricColumn, int $limit, int $offset): array;
}
