<?php

namespace App\Domain\Match\Repository;

use App\Domain\Match\Entity\PlayerMatchStats;
use App\Shared\Domain\MatchId;

interface AnalyticMatchRepository
{
    public function insertMatchStats(PlayerMatchStats $match): void;

    /**
     * @param PlayerMatchStats[] $data
     * @return void
     */
    public function insertMultiplyMatchStats(array $data): void;

    /**
     * @return PlayerMatchStats[]
     */
    public function getPlayerStatsByMatchId(MatchId $matchId): array;
}