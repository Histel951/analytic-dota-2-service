<?php
declare(strict_types=1);

namespace App\Domain\Team\Entity;

use DateTimeImmutable;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;

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
