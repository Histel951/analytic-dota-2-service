<?php

namespace App\Domain\Match\Repository;

use App\Domain\Match\Entity\MatchEntity;
use DateTimeImmutable;
use App\Shared\Domain\MatchId;
use App\Shared\Domain\TeamId;

interface MatchRepository
{
    public function save(MatchEntity $entity): void;

    public function findByMatchId(MatchId $matchId): ?MatchEntity;

    /**
     * @return MatchEntity[]
     */
    public function findByTeamIdAndDateRange(
        TeamId $teamId,
        ?DateTimeImmutable $dateFrom = null,
        ?DateTimeImmutable $dateTo = null
    ): array;
}