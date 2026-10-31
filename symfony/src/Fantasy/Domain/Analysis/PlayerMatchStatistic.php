<?php
declare(strict_types=1);

namespace App\Fantasy\Domain\Analysis;

final readonly class PlayerMatchStatistic
{
    public function __construct(
        private int $kills,
        private int $deaths,
        private int $assists,
        private int $gpm,
        private int $xpm,
        private int $netWorth,
        private int $creepsLastHits,
        private int $creepsDenies,
        private int $opendotaHeroId,
    ) {}

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

    public function getCreepsLastHits(): int
    {
        return $this->creepsLastHits;
    }

    public function getCreepsDenies(): int
    {
        return $this->creepsDenies;
    }

    public function getOpendotaHeroId(): int
    {
        return $this->opendotaHeroId;
    }
}
