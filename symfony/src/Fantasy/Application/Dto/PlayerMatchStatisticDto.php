<?php
declare(strict_types=1);

namespace App\Fantasy\Application\Dto;

final readonly class PlayerMatchStatisticDto
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

    public function toArray(): array
    {
        return [
            'kills' => $this->kills,
            'deaths' => $this->deaths,
            'assists' => $this->assists,
            'gpm' => $this->gpm,
            'xpm' => $this->xpm,
            'net_worth' => $this->netWorth,
            'creeps_last_hits' => $this->creepsLastHits,
            'creeps_denies' => $this->creepsDenies,
            'opendota_hero_id' => $this->opendotaHeroId,
        ];
    }
}
