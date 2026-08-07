<?php
declare(strict_types=1);

namespace App\Domain\Fantasy\ValueObject;

use DateTimeImmutable;

final readonly class PlayerAnalyzedMatch
{
    public function __construct(
        private int $matchId,
        private int $matchDuration,
        private DateTimeImmutable $startTime,
        private PlayerMatchStatistic $statistic,
        private bool $isWin,
    ) {}

    public function getMatchId(): int
    {
        return $this->matchId;
    }

    public function getMatchDuration(): int
    {
        return $this->matchDuration;
    }

    public function getStartTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getStatistic(): PlayerMatchStatistic
    {
        return $this->statistic;
    }

    public function isWin(): bool
    {
        return $this->isWin;
    }
}
