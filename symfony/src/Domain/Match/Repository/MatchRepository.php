<?php

namespace App\Domain\Match\Repository;

use App\Domain\Match\Entity\MatchEntity;
use DateTimeImmutable;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;

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