<?php
declare(strict_types=1);

namespace App\Ingestion\Domain\Team;

use DateTimeImmutable;
use App\Shared\Domain\MatchId;
use App\Shared\Domain\TeamId;

final readonly class TeamMatch
{
    public function __construct(
        private TeamId $teamId,
        private MatchId $matchId,
        private bool $isWin,
        private DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {}

    public function getTeamId(): TeamId
    {
        return $this->teamId;
    }

    public function getMatchId(): MatchId
    {
        return $this->matchId;
    }

    public function isWin(): bool
    {
        return $this->isWin;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
