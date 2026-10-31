<?php
declare(strict_types=1);

namespace App\Fantasy\Domain\Analysis;

use App\Shared\Domain\PlayerId;

final readonly class PlayerInCombo
{
    /**
     * @param PlayerMetrics[] $metrics
     * @param PlayerAnalyzedMatch[] $matches
     */
    public function __construct(
        private PlayerId $playerId,
        private string $nickname,
        private int $role,
        private float $avgTotalScore,
        private array $metrics,
        private int $matchesCount,
        private array $matches = [],
    ) {
        foreach ($metrics as $metric) {
            if (!$metric instanceof PlayerMetrics) {
                throw new \InvalidArgumentException(
                    'All metrics must be instances of PlayerMetrics'
                );
            }
        }

        foreach ($matches as $match) {
            if (!$match instanceof PlayerAnalyzedMatch) {
                throw new \InvalidArgumentException(
                    'All matches must be instances of PlayerAnalyzedMatch'
                );
            }
        }
    }

    public function getPlayerId(): PlayerId
    {
        return $this->playerId;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function getRole(): int
    {
        return $this->role;
    }

    public function getAvgTotalScore(): float
    {
        return $this->avgTotalScore;
    }

    /**
     * @return PlayerMetrics[]
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getMatchesCount(): int
    {
        return $this->matchesCount;
    }

    /**
     * @return PlayerAnalyzedMatch[]
     */
    public function getMatches(): array
    {
        return $this->matches;
    }
}
