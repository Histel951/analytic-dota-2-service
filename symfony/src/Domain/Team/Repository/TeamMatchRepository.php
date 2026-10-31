<?php
declare(strict_types=1);

namespace App\Domain\Team\Repository;

use App\Domain\Team\Entity\TeamMatch;
use DateTimeImmutable;
use App\Shared\Domain\MatchId;
use App\Shared\Domain\TeamId;

interface TeamMatchRepository
{
    public function save(TeamMatch $teamMatch): void;

    /**
     * @param TeamMatch[] $teamMatches
     */
    public function saveBulk(array $teamMatches): void;

    /**
     * @return TeamMatch[]
     */
    public function findByTeamId(
        TeamId $teamId,
        ?DateTimeImmutable $dateFrom = null,
        ?DateTimeImmutable $dateTo = null
    ): array;

    public function exists(TeamId $teamId, MatchId $matchId): bool;
}
