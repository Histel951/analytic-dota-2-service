<?php
declare(strict_types=1);

namespace App\Fantasy\Domain\Scoring;

use App\Ingestion\Domain\Match\PlayerMatchStats;

final readonly class FantasyScoreCalculator
{
    public function __construct(
        private FantasyScoringConfig $config,
    ) {
    }

    public function calculate(PlayerMatchStats $stats): FantasyScoreResult
    {
        $killsScore = $this->calculateKillsScore($stats->getKills());
        $deathsScore = $this->calculateDeathsScore($stats->getDeaths());
        $creepsScore = $this->calculateCreepsScore(
            $stats->getCreepsLastHits(),
            $stats->getCreepsDenies()
        );
        $gpmScore = $this->calculateGpmScore($stats->getGpm());
        $towerScore = $this->calculateTowerScore($stats->getTowerKills());
        $observerScore = $this->calculateObserverScore($stats->getObserversPlaced());
        $stacksScore = $this->calculateStacksScore($stats->getCampsStacked());
        $runeScore = $this->calculateRuneScore($stats->getRunePickups());
        $roshanScore = $this->calculateRoshanScore($stats->getRoshanKills());
        $courierScore = $this->calculateCourierScore($stats->getCourierKills());
        $stunScore = $this->calculateStunScore($stats->getStuns());
        $firstBloodScore = $this->calculateFirstBloodScore($stats->isFirstBloodClaimed());
        $teamfightScore = $this->calculateTeamfightScore($stats->getTeamfightParticipation());
        $tormentorScore = $this->calculateTormentorScore($stats->getTormentorKills());
        $smokeScore = $this->calculateSmokeScore($stats->getSmokeUses());
        $watcherScore = $this->calculateWatcherScore($stats->getObserverTakeovers());

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
            + $teamfightScore
            + $tormentorScore
            + $smokeScore
            + $watcherScore;

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
            tormentorScore: $tormentorScore,
            smokeScore: $smokeScore,
            watcherScore: $watcherScore,
            totalScore: $totalScore,
        );
    }

    private function calculateKillsScore(int $kills): float
    {
        return $kills * $this->config->killsMultiplier;
    }

    private function calculateDeathsScore(int $deaths): float
    {
        $score = $this->config->deathsBase - ($deaths * $this->config->deathsPenalty);
        return max(0, $score);
    }

    private function calculateCreepsScore(int $lastHits, int $denies): float
    {
        return ($lastHits + $denies) * $this->config->creepsMultiplier;
    }

    private function calculateGpmScore(int $gpm): float
    {
        return $gpm * $this->config->gpmMultiplier;
    }

    private function calculateTowerScore(int $towerKills): float
    {
        return $towerKills * $this->config->towerMultiplier;
    }

    private function calculateObserverScore(int $observersPlaced): float
    {
        return $observersPlaced * $this->config->observerMultiplier;
    }

    private function calculateStacksScore(int $campsStacked): float
    {
        return $campsStacked * $this->config->stacksMultiplier;
    }

    private function calculateRuneScore(int $runePickups): float
    {
        return $runePickups * $this->config->runeMultiplier;
    }

    private function calculateRoshanScore(int $roshanKills): float
    {
        return $roshanKills * $this->config->roshanMultiplier;
    }

    private function calculateCourierScore(int $courierKills): float
    {
        return $courierKills * $this->config->courierMultiplier;
    }

    private function calculateStunScore(float $stuns): float
    {
        return $stuns * $this->config->stunMultiplier;
    }

    private function calculateFirstBloodScore(bool $firstBloodClaimed): float
    {
        return $firstBloodClaimed ? $this->config->firstBloodBonus : 0.0;
    }

    private function calculateTeamfightScore(float $teamfightParticipation): float
    {
        $score = $teamfightParticipation * $this->config->teamfightMultiplier;
        return min($score, $this->config->teamfightMaxScore);
    }

    private function calculateTormentorScore(int $tormentorKills): float
    {
        return $tormentorKills * $this->config->tormentorMultiplier;
    }

    private function calculateSmokeScore(int $smokeUses): float
    {
        return $smokeUses * $this->config->smokeMultiplier;
    }

    private function calculateWatcherScore(int $observerTakeovers): float
    {
        return $observerTakeovers * $this->config->watcherMultiplier;
    }
}
