<?php
declare(strict_types=1);

namespace App\Application\PlayerStats\Dto;

final readonly class TopFantasyPlayer
{
    public function __construct(
        private int|string $playerId,
        private array $matches,
        private float $averageTotalScore,
        private array $topMetrics,
    ) {
    }

    public function getPlayerId(): int|string
    {
        return $this->playerId;
    }

    public function getMatches(): array
    {
        return $this->matches;
    }

    public function getAverageTotalScore(): float
    {
        return $this->averageTotalScore;
    }

    public function getTopMetrics(): array
    {
        return $this->topMetrics;
    }

    public function toArray(): array
    {
        return [
            'player_id' => $this->playerId,
            'matches' => $this->matches,
            'average_total_score' => $this->averageTotalScore,
            'metrics' => $this->topMetrics,
        ];
    }
}
