<?php
declare(strict_types=1);

namespace App\Domain\PlayerStats\Entity;

use App\Shared\Domain\PlayerId;

final readonly class PlayerMetric
{
    public function __construct(
        private PlayerId $playerId,
        private float $metricValue,
        private int $matchesCount,
    ) {}

    public function getPlayerId(): PlayerId
    {
        return $this->playerId;
    }

    public function getMetricValue(): float
    {
        return $this->metricValue;
    }

    public function getMatchesCount(): int
    {
        return $this->matchesCount;
    }
}
