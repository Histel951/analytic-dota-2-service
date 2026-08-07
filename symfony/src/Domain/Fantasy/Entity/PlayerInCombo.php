<?php
declare(strict_types=1);

namespace App\Domain\Fantasy\Entity;

use App\Domain\Fantasy\ValueObject\PlayerMetrics;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerId;

final readonly class PlayerInCombo
{
    /**
     * @param PlayerMetrics[] $metrics
     */
    public function __construct(
        private PlayerId $playerId,
        private string $nickname,
        private int $role,
        private float $avgTotalScore,
        private array $metrics,
        private int $matchesCount,
    ) {
        foreach ($metrics as $metric) {
            if (!$metric instanceof PlayerMetrics) {
                throw new \InvalidArgumentException(
                    'All metrics must be instances of PlayerMetrics'
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
}
