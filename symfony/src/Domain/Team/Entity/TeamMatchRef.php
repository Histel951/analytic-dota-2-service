<?php

declare(strict_types=1);

namespace App\Domain\Team\Entity;

use App\Shared\Domain\MatchId;
use DateTimeImmutable;

/**
 * Матч в истории команды по данным источника.
 */
final readonly class TeamMatchRef
{
    public function __construct(
        private MatchId $matchId,
        private DateTimeImmutable $startTime,
        private bool $won,
    ) {
    }

    public function getMatchId(): MatchId
    {
        return $this->matchId;
    }

    public function getStartTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function isWon(): bool
    {
        return $this->won;
    }
}
