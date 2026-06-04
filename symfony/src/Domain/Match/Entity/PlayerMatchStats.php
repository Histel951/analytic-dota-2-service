<?php
declare(strict_types = 1);

namespace App\Domain\Match\Entity;

use DateTimeImmutable;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Duration;
use Histel951\Dota2Api\Domain\Common\ValueObjects\GoldSpent;
use Histel951\Dota2Api\Domain\Common\ValueObjects\GPM;
use Histel951\Dota2Api\Domain\Common\ValueObjects\HeroId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\NetWorth;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Role;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TotalGold;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TotalXP;
use Histel951\Dota2Api\Domain\Common\ValueObjects\XPM;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Lane;
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Side;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\AncientCreeps;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\BuybackCount;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\CourierKills;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\Denies;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\HeroDamage;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\HeroHealing;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\LaneCreeps;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\LastHits;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\NeutralCreeps;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\ObserversDestroyed;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\ObserversPlaced;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\RoshanKills;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\RunePickups;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\SentryDestroyed;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\SentryPlaced;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\Stuns;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TeamfightParticipation;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TowerDamage;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TowerKills;

final readonly class PlayerMatchStats
{
    public function __construct(
        private MatchId $matchId,
        private Duration $duration,
        private PlayerId $playerId,

        private HeroId $openDotaHeroId,
        private TeamId $openDotaTeamId,

        private int $kills,
        private int $deaths,
        private int $assists,

        private GPM $gpm,
        private XPM $xpm,
        private NetWorth $netWorth,
        private TotalGold $totalGold,
        private TotalXP $totalXp,
        private GoldSpent $goldSpend,

        private LastHits $creepsLastHits,
        private LaneCreeps $creepsLaneLastHits,
        private Denies $creepsDenies,
        private NeutralCreeps $creepsNeutrals,
        private AncientCreeps $creepsAncients,

        private RoshanKills $roshanKills,
        private TowerKills $towerKills,
        private RunePickups $runePickups,

        private ObserversPlaced $observersPlaced,
        private ObserversDestroyed $observersDestroyed,
        private SentryPlaced $sentryPlaced,
        private SentryDestroyed $sentryDestroyed,

        private HeroDamage $heroDamage,
        private TowerDamage $towerDamage,
        private HeroHealing $heroHealing,

        private BuybackCount $buybackCount,
        private bool $firstBloodClaimed,
        private CourierKills $courierKills,
        private Stuns $stuns,
        private TeamfightParticipation $teamfightParticipation,

        private Side $side,
        private bool $win,

        private Lane $lane,
        private Role $role,

        private DateTimeImmutable $startTime,
    ) {
    }

    public function getMatchId(): MatchId
    {
        return $this->matchId;
    }

    public function getDuration(): Duration
    {
        return $this->duration;
    }

    public function getPlayerId(): PlayerId
    {
        return $this->playerId;
    }

    public function getOpenDotaHeroId(): HeroId
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

    public function getGpm(): GPM
    {
        return $this->gpm;
    }

    public function getXpm(): XPM
    {
        return $this->xpm;
    }

    public function getNetWorth(): NetWorth
    {
        return $this->netWorth;
    }

    public function getTotalGold(): TotalGold
    {
        return $this->totalGold;
    }

    public function getTotalXp(): TotalXP
    {
        return $this->totalXp;
    }

    public function getGoldSpend(): GoldSpent
    {
        return $this->goldSpend;
    }

    public function getCreepsLastHits(): LastHits
    {
        return $this->creepsLastHits;
    }

    public function getCreepsLaneLastHits(): LaneCreeps
    {
        return $this->creepsLaneLastHits;
    }

    public function getCreepsDenies(): Denies
    {
        return $this->creepsDenies;
    }

    public function getCreepsNeutrals(): NeutralCreeps
    {
        return $this->creepsNeutrals;
    }

    public function getCreepsAncients(): AncientCreeps
    {
        return $this->creepsAncients;
    }

    public function getRoshanKills(): RoshanKills
    {
        return $this->roshanKills;
    }

    public function getTowerKills(): TowerKills
    {
        return $this->towerKills;
    }

    public function getRunePickups(): RunePickups
    {
        return $this->runePickups;
    }

    public function getObserversPlaced(): ObserversPlaced
    {
        return $this->observersPlaced;
    }

    public function getObserversDestroyed(): ObserversDestroyed
    {
        return $this->observersDestroyed;
    }

    public function getSentryPlaced(): SentryPlaced
    {
        return $this->sentryPlaced;
    }

    public function getSentryDestroyed(): SentryDestroyed
    {
        return $this->sentryDestroyed;
    }

    public function getHeroDamage(): HeroDamage
    {
        return $this->heroDamage;
    }

    public function getTowerDamage(): TowerDamage
    {
        return $this->towerDamage;
    }

    public function getHeroHealing(): HeroHealing
    {
        return $this->heroHealing;
    }

    public function getBuybackCount(): BuybackCount
    {
        return $this->buybackCount;
    }

    public function isFirstBloodClaimed(): bool
    {
        return $this->firstBloodClaimed;
    }

    public function getCourierKills(): CourierKills
    {
        return $this->courierKills;
    }

    public function getStuns(): Stuns
    {
        return $this->stuns;
    }

    public function getTeamfightParticipation(): TeamfightParticipation
    {
        return $this->teamfightParticipation;
    }

    public function getSide(): Side
    {
        return $this->side;
    }

    public function isWin(): bool
    {
        return $this->win;
    }

    public function getLane(): Lane
    {
        return $this->lane;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getStartTime(): DateTimeImmutable
    {
        return $this->startTime;
    }
}