<?php
declare(strict_types=1);

namespace App\Domain\Match\ValueObject;

final readonly class FantasyScoringConfig
{
    public function __construct(
        public int $killsMultiplier,
        public int $deathsBase,
        public int $deathsPenalty,
        public int $creepsMultiplier,
        public int $gpmMultiplier,
        public int $towerMultiplier,
        public int $observerMultiplier,
        public int $stacksMultiplier,
        public int $runeMultiplier,
        public int $roshanMultiplier,
        public int $courierMultiplier,
        public int $stunMultiplier,
        public int $firstBloodBonus,
        public int $teamfightMultiplier,
        public int $teamfightMaxScore,
        public int $tormentorMultiplier,
        public int $smokeMultiplier,
        public int $watcherMultiplier,
    ) {
    }
}
