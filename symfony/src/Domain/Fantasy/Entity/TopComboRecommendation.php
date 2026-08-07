<?php
declare(strict_types=1);

namespace App\Domain\Fantasy\Entity;

use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;

final readonly class TopComboRecommendation
{
    /**
     * @param PlayerInCombo[] $players
     */
    public function __construct(
        private TeamId $teamId,
        private array $players,
        private float $avgCombinedScore,
        private int $matchesCount,
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
    }

    public function getTeamId(): TeamId
    {
        return $this->teamId;
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
}
