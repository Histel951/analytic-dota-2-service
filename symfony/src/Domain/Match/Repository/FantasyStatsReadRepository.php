<?php
declare(strict_types=1);

namespace App\Domain\Match\Repository;

interface FantasyStatsReadRepository
{
    /**
     * @return array<array<string, mixed>>
     */
    public function findTopFantasyPlayers(int $limit, int $matches, int $offset): array;

    /**
     * @return array<array<string, mixed>>
     */
    public function findFantasyStatsByMatchId(int $matchId, int $limit, int $offset): array;
}
