<?php
declare(strict_types=1);

namespace App\Infrastructure\ClickHouse\Repository;

use App\Domain\Match\Entity\PlayerMatchStats;
use App\Domain\Match\Repository\AnalyticMatchRepository;
use ClickHouseDB\Client;
use DateTimeImmutable;
use Histel951\Dota2Api\Domain\Common\Enums\PlayerRole;
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
use Histel951\Dota2Api\Domain\Entities\Match\Enums\Side;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\AncientCreeps;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\BuybackCount;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\CampsStacked;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\CourierKills;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\Denies;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\HeroDamage;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\HeroHealing;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\LaneCreeps;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\LastHits;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\NeutralCreeps;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\ObserversDestroyed;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\ObserversPlaced;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\ObserverTakeovers;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\RoshanKills;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\RunePickups;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\SentryDestroyed;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\SentryPlaced;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\SmokeUses;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\Stuns;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TeamfightParticipation;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TormentorKills;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TowerDamage;
use Histel951\Dota2Api\Domain\Entities\Match\ValueObjects\TowerKills;

final readonly class AnalyticMatchAnalyticsRepository implements AnalyticMatchRepository
{
    public function __construct(
        private Client $clickhouse,
    ) {}

    private const COLUMNS = [
        'match_id',
        'duration',
        'player_id',
        'opendota_hero_id',
        'opendota_team_id',
        'kills',
        'deaths',
        'assists',
        'gpm',
        'xpm',
        'net_worth',
        'total_gold',
        'total_xp',
        'gold_spend',
        'creeps_last_hits',
        'creeps_lane_last_hits',
        'creeps_denies',
        'creeps_neutrals',
        'creeps_ancients',
        'camps_stacked',
        'roshan_kills',
        'tower_kills',
        'rune_pickups',
        'tormentor_kills',
        'observers_placed',
        'observers_destroyed',
        'sentry_placed',
        'sentry_destroyed',
        'observer_takeovers',
        'hero_damage',
        'tower_damage',
        'hero_healing',
        'buyback_count',
        'first_blood_claimed',
        'courier_kills',
        'stuns',
        'teamfight_participation',
        'smoke_uses',
        'side',
        'win',
        'lane',
        'role',
        'start_time',
    ];

    public function insertMatchStats(PlayerMatchStats $match): void
    {
        $this->clickhouse->insert(
            'player_match_stats',
            [
                $this->createStatsData($match)
            ],
            self::COLUMNS
        );
    }

    public function insertMultiplyMatchStats(array $data): void
    {
        $rows = [];
        foreach ($data as $match) {
            $rows[] = $this->createStatsData($match);
        }

        $this->clickhouse->insert(
            'player_match_stats',
            $rows,
            self::COLUMNS
        );
    }

    private function createStatsData(PlayerMatchStats $match): array
    {
        return [
            'match_id' => $match->getMatchId()->getValue(),
            'duration' => $match->getDuration()->getSeconds(),
            'player_id' => $match->getPlayerId()->getValue(),

            'opendota_hero_id' => $match->getOpenDotaHeroId()->getValue(),
            'opendota_team_id' => $match->getOpenDotaTeamId()->getValue(),

            'kills' => $match->getKills(),
            'deaths' => $match->getDeaths(),
            'assists' => $match->getAssists(),

            'gpm' => $match->getGpm()->getValue(),
            'xpm' => $match->getXpm()->getValue(),
            'net_worth' => $match->getNetWorth()->getValue(),
            'total_gold' => $match->getTotalGold()->getValue(),
            'total_xp' => $match->getTotalXp()->getValue(),
            'gold_spend' => $match->getGoldSpend()->getValue(),

            'creeps_last_hits' => $match->getCreepsLastHits()->getValue(),
            'creeps_lane_last_hits' => $match->getCreepsLaneLastHits()->getValue(),
            'creeps_denies' => $match->getCreepsDenies()->getValue(),
            'creeps_neutrals' => $match->getCreepsNeutrals()->getValue(),
            'creeps_ancients' => $match->getCreepsAncients()->getValue(),
            'camps_stacked' => $match->getCampsStacked()->getValue(),

            'roshan_kills' => $match->getRoshanKills()->getValue(),
            'tower_kills' => $match->getTowerKills()->getValue(),
            'rune_pickups' => $match->getRunePickups()->getValue(),
            'tormentor_kills' => $match->getTormentorKills()->getValue(),

            'observers_placed' => $match->getObserversPlaced()->getValue(),
            'observers_destroyed' => $match->getObserversDestroyed()->getValue(),
            'sentry_placed' => $match->getSentryPlaced()->getValue(),
            'sentry_destroyed' => $match->getSentryDestroyed()->getValue(),
            'observer_takeovers' => $match->getObserverTakeovers()->getValue(),

            'hero_damage' => $match->getHeroDamage()->getValue(),
            'tower_damage' => $match->getTowerDamage()->getValue(),
            'hero_healing' => $match->getHeroHealing()->getValue(),

            'buyback_count' => $match->getBuybackCount()->getValue(),
            'first_blood_claimed' => (int)$match->isFirstBloodClaimed(),
            'courier_kills' => $match->getCourierKills()->getValue(),
            'stuns' => $match->getStuns()->getValue(),
            'teamfight_participation' => $match->getTeamfightParticipation()->getValue(),
            'smoke_uses' => $match->getSmokeUses()->getValue(),

            'side' => $match->getSide()->value,
            'win' => (int)$match->isWin(),

            'lane' => $match->getRole()->toInt(),
            'role' => $match->getRole()->toInt(),

            'start_time' => $match->getStartTime()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param MatchId $matchId
     * @return PlayerMatchStats[]
     */
    public function getPlayerStatsByMatchId(MatchId $matchId): array
    {
        $query = "SELECT * FROM player_match_stats WHERE match_id = :match_id";
        
        $statement = $this->clickhouse->select($query, [
            'match_id' => $matchId->getValue(),
        ]);

        $rows = $statement->rows();
        
        if (empty($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->mapRowToPlayerMatchStats($row);
        }

        return $result;
    }

    private function mapRowToPlayerMatchStats(array $row): PlayerMatchStats
    {
        return new PlayerMatchStats(
            matchId: new MatchId($row['match_id']),
            duration: Duration::fromSeconds($row['duration']),
            playerId: new PlayerId($row['player_id']),
            openDotaHeroId: new HeroId($row['opendota_hero_id']),
            openDotaTeamId: new TeamId($row['opendota_team_id']),
            kills: (int)$row['kills'],
            deaths: (int)$row['deaths'],
            assists: (int)$row['assists'],
            gpm: new GPM($row['gpm']),
            xpm: new XPM($row['xpm']),
            netWorth: new NetWorth($row['net_worth']),
            totalGold: new TotalGold($row['total_gold']),
            totalXp: new TotalXP($row['total_xp']),
            goldSpend: new GoldSpent($row['gold_spend']),
            creepsLastHits: new LastHits($row['creeps_last_hits']),
            creepsLaneLastHits: new LaneCreeps($row['creeps_lane_last_hits']),
            creepsDenies: new Denies($row['creeps_denies']),
            creepsNeutrals: new NeutralCreeps($row['creeps_neutrals']),
            creepsAncients: new AncientCreeps($row['creeps_ancients']),
            campsStacked: new CampsStacked($row['camps_stacked']),
            roshanKills: new RoshanKills($row['roshan_kills']),
            towerKills: new TowerKills($row['tower_kills']),
            runePickups: new RunePickups($row['rune_pickups']),
            tormentorKills: new TormentorKills($row['tormentor_kills'] ?? 0),
            observersPlaced: new ObserversPlaced($row['observers_placed']),
            observersDestroyed: new ObserversDestroyed($row['observers_destroyed']),
            sentryPlaced: new SentryPlaced($row['sentry_placed']),
            sentryDestroyed: new SentryDestroyed($row['sentry_destroyed']),
            observerTakeovers: new ObserverTakeovers($row['observer_takeovers'] ?? 0),
            heroDamage: new HeroDamage($row['hero_damage']),
            towerDamage: new TowerDamage($row['tower_damage']),
            heroHealing: new HeroHealing($row['hero_healing']),
            buybackCount: new BuybackCount($row['buyback_count']),
            firstBloodClaimed: (bool)$row['first_blood_claimed'],
            courierKills: new CourierKills($row['courier_kills']),
            stuns: new Stuns($row['stuns']),
            teamfightParticipation: new TeamfightParticipation($row['teamfight_participation']),
            smokeUses: new SmokeUses($row['smoke_uses'] ?? 0),
            side: Side::from((int)$row['side']),
            win: (bool)$row['win'],
            role: new Role(PlayerRole::from($row['role'])),
            startTime: new DateTimeImmutable($row['start_time']),
        );
    }
}