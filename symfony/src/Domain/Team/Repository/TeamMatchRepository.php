<?php
declare(strict_types=1);

namespace App\Domain\Team\Repository;

use App\Domain\Team\Entity\TeamMatch;
use DateTimeImmutable;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;

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
