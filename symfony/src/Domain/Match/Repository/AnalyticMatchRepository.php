<?php

namespace App\Domain\Match\Repository;

use App\Domain\Match\Entity\PlayerMatchStats;

interface AnalyticMatchRepository
{
    public function insertMatchStats(PlayerMatchStats $match): void;

    /**
     * @param PlayerMatchStats[] $data
     * @return void
     */
    public function insertMultiplyMatchStats(array $data): void;
}