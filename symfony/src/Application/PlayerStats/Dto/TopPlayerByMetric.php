<?php
declare(strict_types=1);

namespace App\Application\PlayerStats\Dto;

final readonly class TopPlayerByMetric
{
    public function __construct(
        private int|string $playerId,
        private string $metric,
        private float $value,
        private int $matchesCount,
    ) {}

    public function getPlayerId(): int|string
    {
        return $this->playerId;
    }

    public function getMetric(): string
    {
        return $this->metric;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getMatchesCount(): int
    {
        return $this->matchesCount;
    }

    public function toArray(): array
    {
        return [
            'player_id' => $this->playerId,
            'metric' => $this->metric,
            'value' => $this->value,
            'matches_count' => $this->matchesCount,
        ];
    }
}
