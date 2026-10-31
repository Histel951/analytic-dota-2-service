<?php

declare(strict_types = 1);

namespace App\Domain\Match\Entity;

use App\Shared\Domain\MatchId;
use App\Shared\Domain\PlayerId;
use App\Shared\Domain\PlayerRole;
use App\Shared\Domain\Side;
use App\Shared\Domain\TeamId;
use DateTimeImmutable;

/**
 * Статистика игрока в матче — строка player_match_stats.
 * Счётчики — неотрицательные целые, stuns — секунды, teamfightParticipation — доля 0..1.
 */
final readonly class PlayerMatchStats
{
    public function __construct(
        private MatchId $matchId,
        private int $durationSeconds,
        private PlayerId $playerId,
        private int $openDotaHeroId,
        private TeamId $openDotaTeamId,
        private int $kills,
        private int $deaths,
        private int $assists,
        private int $gpm,
        private int $xpm,
        private int $netWorth,
        private int $totalGold,
        private int $totalXp,
        private int $goldSpend,
        private int $creepsLastHits,
        private int $creepsLaneLastHits,
        private int $creepsDenies,
        private int $creepsNeutrals,
        private int $creepsAncients,
        private int $campsStacked,
        private int $roshanKills,
        private int $towerKills,
        private int $runePickups,
        private int $tormentorKills,
        private int $observersPlaced,
        private int $observersDestroyed,
        private int $sentryPlaced,
        private int $sentryDestroyed,
        private int $observerTakeovers,
        private int $heroDamage,
        private int $towerDamage,
        private int $heroHealing,
        private int $buybackCount,
        private bool $firstBloodClaimed,
        private int $courierKills,
        private float $stuns,
        private float $teamfightParticipation,
        private int $smokeUses,
        private Side $side,
        private bool $win,
        private PlayerRole $role,
        private DateTimeImmutable $startTime,
    ) {
    }

    public function getMatchId(): MatchId
    {
        return $this->matchId;
    }

    public function getDurationSeconds(): int
    {
        return $this->durationSeconds;
    }

    public function getPlayerId(): PlayerId
    {
        return $this->playerId;
    }

    public function getOpenDotaHeroId(): int
    {
        return $this->openDotaHeroId;
    }

    public function getOpenDotaTeamId(): TeamId
    {
        return $this->openDotaTeamId;
    }

    public function getKills(): int
    {
        return $this->kills;
    }

    public function getDeaths(): int
    {
        return $this->deaths;
    }

    public function getAssists(): int
    {
        return $this->assists;
    }

    public function getGpm(): int
    {
        return $this->gpm;
    }

    public function getXpm(): int
    {
        return $this->xpm;
    }

    public function getNetWorth(): int
    {
        return $this->netWorth;
    }

    public function getTotalGold(): int
    {
        return $this->totalGold;
    }

    public function getTotalXp(): int
    {
        return $this->totalXp;
    }

    public function getGoldSpend(): int
    {
        return $this->goldSpend;
    }

    public function getCreepsLastHits(): int
    {
        return $this->creepsLastHits;
    }

    public function getCreepsLaneLastHits(): int
    {
        return $this->creepsLaneLastHits;
    }

    public function getCreepsDenies(): int
    {
        return $this->creepsDenies;
    }

    public function getCreepsNeutrals(): int
    {
        return $this->creepsNeutrals;
    }

    public function getCreepsAncients(): int
    {
        return $this->creepsAncients;
    }

    public function getCampsStacked(): int
    {
        return $this->campsStacked;
    }

    public function getRoshanKills(): int
    {
        return $this->roshanKills;
    }

    public function getTowerKills(): int
    {
        return $this->towerKills;
    }

    public function getRunePickups(): int
    {
        return $this->runePickups;
    }

    public function getTormentorKills(): int
    {
        return $this->tormentorKills;
    }

    public function getObserversPlaced(): int
    {
        return $this->observersPlaced;
    }

    public function getObserversDestroyed(): int
    {
        return $this->observersDestroyed;
    }

    public function getSentryPlaced(): int
    {
        return $this->sentryPlaced;
    }

    public function getSentryDestroyed(): int
    {
        return $this->sentryDestroyed;
    }

    public function getObserverTakeovers(): int
    {
        return $this->observerTakeovers;
    }

    public function getHeroDamage(): int
    {
        return $this->heroDamage;
    }

    public function getTowerDamage(): int
    {
        return $this->towerDamage;
    }

    public function getHeroHealing(): int
    {
        return $this->heroHealing;
    }

    public function getBuybackCount(): int
    {
        return $this->buybackCount;
    }

    public function isFirstBloodClaimed(): bool
    {
        return $this->firstBloodClaimed;
    }

    public function getCourierKills(): int
    {
        return $this->courierKills;
    }

    public function getStuns(): float
    {
        return $this->stuns;
    }

    public function getTeamfightParticipation(): float
    {
        return $this->teamfightParticipation;
    }

    public function getSmokeUses(): int
    {
        return $this->smokeUses;
    }

    public function getSide(): Side
    {
        return $this->side;
    }

    public function isWin(): bool
    {
        return $this->win;
    }

    public function getRole(): PlayerRole
    {
        return $this->role;
    }

    public function getStartTime(): DateTimeImmutable
    {
        return $this->startTime;
    }
}
