<?php
declare(strict_types=1);

namespace App\Domain\Match\Service;

use App\Domain\Match\Dto\FantasyScoreResult;
use App\Domain\Match\Entity\PlayerMatchStats;

final readonly class FantasyScoreCalculator
{
    private const KILLS_MULTIPLIER = 107;
    private const DEATHS_BASE = 1950;
    private const DEATHS_PENALTY = 195;
    private const CREEPS_MULTIPLIER = 3;
    private const GPM_MULTIPLIER = 2;
    private const TOWER_MULTIPLIER = 352;
    private const OBSERVER_MULTIPLIER = 117;
    private const STACKS_MULTIPLIER = 234;
    private const RUNE_MULTIPLIER = 141;
    private const ROSHAN_MULTIPLIER = 1172;
    private const COURIER_MULTIPLIER = 703;
    private const STUN_MULTIPLIER = 10;
    private const FIRST_BLOOD_BONUS = 1934;
    private const TEAMFIGHT_MULTIPLIER = 2124;
    private const TEAMFIGHT_MAX_SCORE = 2124;

    public function calculate(PlayerMatchStats $stats): FantasyScoreResult
    {
        $killsScore = $this->calculateKillsScore($stats->getKills());
        $deathsScore = $this->calculateDeathsScore($stats->getDeaths());
        $creepsScore = $this->calculateCreepsScore(
            $stats->getCreepsLastHits()->getValue(),
            $stats->getCreepsDenies()->getValue()
        );
        $gpmScore = $this->calculateGpmScore($stats->getGpm()->getValue());
        $towerScore = $this->calculateTowerScore($stats->getTowerKills()->getValue());
        $observerScore = $this->calculateObserverScore($stats->getObserversPlaced()->getValue());
        $stacksScore = $this->calculateStacksScore($stats->getCampsStacked()->getValue());
        $runeScore = $this->calculateRuneScore($stats->getRunePickups()->getValue());
        $roshanScore = $this->calculateRoshanScore($stats->getRoshanKills()->getValue());
        $courierScore = $this->calculateCourierScore($stats->getCourierKills()->getValue());
        $stunScore = $this->calculateStunScore($stats->getStuns()->getValue());
        $firstBloodScore = $this->calculateFirstBloodScore($stats->isFirstBloodClaimed());
        $teamfightScore = $this->calculateTeamfightScore($stats->getTeamfightParticipation()->getValue());

        $totalScore = $killsScore
            + $deathsScore
            + $creepsScore
            + $gpmScore
            + $towerScore
            + $observerScore
            + $stacksScore
            + $runeScore
            + $roshanScore
            + $courierScore
            + $stunScore
            + $firstBloodScore
            + $teamfightScore;

        return new FantasyScoreResult(
            killsScore: $killsScore,
            deathsScore: $deathsScore,
            creepsScore: $creepsScore,
            gpmScore: $gpmScore,
            towerScore: $towerScore,
            observerScore: $observerScore,
            stacksScore: $stacksScore,
            runeScore: $runeScore,
            roshanScore: $roshanScore,
            courierScore: $courierScore,
            stunScore: $stunScore,
            firstBloodScore: $firstBloodScore,
            teamfightScore: $teamfightScore,
            totalScore: $totalScore,
        );
    }

    private function calculateKillsScore(int $kills): float
    {
        return $kills * self::KILLS_MULTIPLIER;
    }

    private function calculateDeathsScore(int $deaths): float
    {
        return self::DEATHS_BASE - ($deaths * self::DEATHS_PENALTY);
    }

    private function calculateCreepsScore(int $lastHits, int $denies): float
    {
        return ($lastHits + $denies) * self::CREEPS_MULTIPLIER;
    }

    private function calculateGpmScore(int $gpm): float
    {
        return $gpm * self::GPM_MULTIPLIER;
    }

    private function calculateTowerScore(int $towerKills): float
    {
        return $towerKills * self::TOWER_MULTIPLIER;
    }

    private function calculateObserverScore(int $observersPlaced): float
    {
        return $observersPlaced * self::OBSERVER_MULTIPLIER;
    }

    private function calculateStacksScore(int $campsStacked): float
    {
        return $campsStacked * self::STACKS_MULTIPLIER;
    }

    private function calculateRuneScore(int $runePickups): float
    {
        return $runePickups * self::RUNE_MULTIPLIER;
    }

    private function calculateRoshanScore(int $roshanKills): float
    {
        return $roshanKills * self::ROSHAN_MULTIPLIER;
    }

    private function calculateCourierScore(int $courierKills): float
    {
        return $courierKills * self::COURIER_MULTIPLIER;
    }

    private function calculateStunScore(float $stuns): float
    {
        return $stuns * self::STUN_MULTIPLIER;
    }

    private function calculateFirstBloodScore(bool $firstBloodClaimed): float
    {
        return $firstBloodClaimed ? self::FIRST_BLOOD_BONUS : 0.0;
    }

    private function calculateTeamfightScore(float $teamfightParticipation): float
    {
        $score = $teamfightParticipation * self::TEAMFIGHT_MULTIPLIER;
        return min($score, self::TEAMFIGHT_MAX_SCORE);
    }
}
