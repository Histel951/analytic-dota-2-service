<?php
declare(strict_types=1);

namespace App\Fantasy\Domain\Scoring;

final readonly class FantasyScoreResult
{
    public function __construct(
        public float $killsScore,
        public float $deathsScore,
        public float $creepsScore,
        public float $gpmScore,
        public float $towerScore,
        public float $observerScore,
        public float $stacksScore,
        public float $runeScore,
        public float $roshanScore,
        public float $courierScore,
        public float $stunScore,
        public float $firstBloodScore,
        public float $teamfightScore,
        public float $tormentorScore,
        public float $smokeScore,
        public float $watcherScore,
        public float $totalScore,
    ) {
    }

    public function getTotalScore(): float
    {
        return $this->totalScore;
    }
}
