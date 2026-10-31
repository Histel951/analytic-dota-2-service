<?php
declare(strict_types=1);

namespace App\Infrastructure\ClickHouse\Repository;

use App\Domain\Match\Entity\PlayerMatchStats;
use App\Domain\Match\Repository\AnalyticMatchRepository;
use App\Shared\Domain\MatchId;
use App\Shared\Domain\PlayerId;
use App\Shared\Domain\PlayerRole;
use App\Shared\Domain\Side;
use App\Shared\Domain\TeamId;
use ClickHouseDB\Client;
use DateTimeImmutable;

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
            'duration' => $match->getDurationSeconds(),
            'player_id' => $match->getPlayerId()->getValue(),

            'opendota_hero_id' => $match->getOpenDotaHeroId(),
            'opendota_team_id' => $match->getOpenDotaTeamId()->getValue(),

            'kills' => $match->getKills(),
            'deaths' => $match->getDeaths(),
            'assists' => $match->getAssists(),

            'gpm' => $match->getGpm(),
            'xpm' => $match->getXpm(),
            'net_worth' => $match->getNetWorth(),
            'total_gold' => $match->getTotalGold(),
            'total_xp' => $match->getTotalXp(),
            'gold_spend' => $match->getGoldSpend(),

            'creeps_last_hits' => $match->getCreepsLastHits(),
            'creeps_lane_last_hits' => $match->getCreepsLaneLastHits(),
            'creeps_denies' => $match->getCreepsDenies(),
            'creeps_neutrals' => $match->getCreepsNeutrals(),
            'creeps_ancients' => $match->getCreepsAncients(),
            'camps_stacked' => $match->getCampsStacked(),

            'roshan_kills' => $match->getRoshanKills(),
            'tower_kills' => $match->getTowerKills(),
            'rune_pickups' => $match->getRunePickups(),
            'tormentor_kills' => $match->getTormentorKills(),

            'observers_placed' => $match->getObserversPlaced(),
            'observers_destroyed' => $match->getObserversDestroyed(),
            'sentry_placed' => $match->getSentryPlaced(),
            'sentry_destroyed' => $match->getSentryDestroyed(),
            'observer_takeovers' => $match->getObserverTakeovers(),

            'hero_damage' => $match->getHeroDamage(),
            'tower_damage' => $match->getTowerDamage(),
            'hero_healing' => $match->getHeroHealing(),

            'buyback_count' => $match->getBuybackCount(),
            'first_blood_claimed' => (int)$match->isFirstBloodClaimed(),
            'courier_kills' => $match->getCourierKills(),
            'stuns' => $match->getStuns(),
            'teamfight_participation' => $match->getTeamfightParticipation(),
            'smoke_uses' => $match->getSmokeUses(),

            'side' => $match->getSide()->value,
            'win' => (int)$match->isWin(),

            'lane' => $match->getRole()->value,
            'role' => $match->getRole()->value,

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
            durationSeconds: (int) $row['duration'],
            playerId: new PlayerId($row['player_id']),
            openDotaHeroId: (int) ($row['opendota_hero_id']),
            openDotaTeamId: new TeamId($row['opendota_team_id']),
            kills: (int)$row['kills'],
            deaths: (int)$row['deaths'],
            assists: (int)$row['assists'],
            gpm: (int) ($row['gpm']),
            xpm: (int) ($row['xpm']),
            netWorth: (int) ($row['net_worth']),
            totalGold: (int) ($row['total_gold']),
            totalXp: (int) ($row['total_xp']),
            goldSpend: (int) ($row['gold_spend']),
            creepsLastHits: (int) ($row['creeps_last_hits']),
            creepsLaneLastHits: (int) ($row['creeps_lane_last_hits']),
            creepsDenies: (int) ($row['creeps_denies']),
            creepsNeutrals: (int) ($row['creeps_neutrals']),
            creepsAncients: (int) ($row['creeps_ancients']),
            campsStacked: (int) ($row['camps_stacked']),
            roshanKills: (int) ($row['roshan_kills']),
            towerKills: (int) ($row['tower_kills']),
            runePickups: (int) ($row['rune_pickups']),
            tormentorKills: (int) ($row['tormentor_kills'] ?? 0),
            observersPlaced: (int) ($row['observers_placed']),
            observersDestroyed: (int) ($row['observers_destroyed']),
            sentryPlaced: (int) ($row['sentry_placed']),
            sentryDestroyed: (int) ($row['sentry_destroyed']),
            observerTakeovers: (int) ($row['observer_takeovers'] ?? 0),
            heroDamage: (int) ($row['hero_damage']),
            towerDamage: (int) ($row['tower_damage']),
            heroHealing: (int) ($row['hero_healing']),
            buybackCount: (int) ($row['buyback_count']),
            firstBloodClaimed: (bool)$row['first_blood_claimed'],
            courierKills: (int) ($row['courier_kills']),
            stuns: (float) $row['stuns'],
            teamfightParticipation: (float) $row['teamfight_participation'],
            smokeUses: (int) ($row['smoke_uses'] ?? 0),
            side: Side::from((int)$row['side']),
            win: (bool)$row['win'],
            role: PlayerRole::from((int) $row['role']),
            startTime: new DateTimeImmutable($row['start_time']),
        );
    }
}