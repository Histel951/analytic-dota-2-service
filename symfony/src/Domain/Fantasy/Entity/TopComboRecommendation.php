<?php
declare(strict_types=1);

namespace App\Domain\Fantasy\Entity;

use App\Domain\Fantasy\ValueObject\MetricContribution;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;

final readonly class TopComboRecommendation
{
    /**
     * @param PlayerInCombo[] $players
     * @param MetricContribution[] $topMetrics
     */
    public function __construct(
        private TeamId $teamId,
        private array $topMetrics,
        private array $players,
        private float $avgCombinedScore,
        private int $matchesCount,
        private float $avgMatchDuration,
    ) {
        if (empty($players)) {
            throw new \InvalidArgumentException('Players array cannot be empty');
        }

        foreach ($players as $player) {
            if (!$player instanceof PlayerInCombo) {
                throw new \InvalidArgumentException(
                    'All players must be instances of PlayerInCombo'
                );
            }
        }

        foreach ($topMetrics as $metric) {
            if (!$metric instanceof MetricContribution) {
                throw new \InvalidArgumentException(
                    'All top metrics must be instances of MetricContribution'
                );
            }
        }
    }

    public function getTeamId(): TeamId
    {
        return $this->teamId;
    }

    /**
     * @return MetricContribution[]
     */
    public function getTopMetrics(): array
    {
        return $this->topMetrics;
    }

    /**
     * @return PlayerInCombo[]
     */
    public function getPlayers(): array
    {
        return $this->players;
    }

    public function getAvgCombinedScore(): float
    {
        return $this->avgCombinedScore;
    }

    public function getMatchesCount(): int
    {
        return $this->matchesCount;
    }

    public function getAvgMatchDuration(): float
    {
        return $this->avgMatchDuration;
    }
}
