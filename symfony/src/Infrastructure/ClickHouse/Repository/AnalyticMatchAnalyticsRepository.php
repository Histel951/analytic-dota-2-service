<?php
declare(strict_types=1);

namespace App\Infrastructure\ClickHouse\Repository;

use App\Domain\Match\Entity\PlayerMatchStats;
use App\Domain\Match\Repository\AnalyticMatchRepository;
use ClickHouseDB\Client;

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
        'observers_placed',
        'observers_destroyed',
        'sentry_placed',
        'sentry_destroyed',
        'hero_damage',
        'tower_damage',
        'hero_healing',
        'buyback_count',
        'first_blood_claimed',
        'courier_kills',
        'stuns',
        'teamfight_participation',
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

            'observers_placed' => $match->getObserversPlaced()->getValue(),
            'observers_destroyed' => $match->getObserversDestroyed()->getValue(),
            'sentry_placed' => $match->getSentryPlaced()->getValue(),
            'sentry_destroyed' => $match->getSentryDestroyed()->getValue(),

            'hero_damage' => $match->getHeroDamage()->getValue(),
            'tower_damage' => $match->getTowerDamage()->getValue(),
            'hero_healing' => $match->getHeroHealing()->getValue(),

            'buyback_count' => $match->getBuybackCount()->getValue(),
            'first_blood_claimed' => (int)$match->isFirstBloodClaimed(),
            'courier_kills' => $match->getCourierKills()->getValue(),
            'stuns' => $match->getStuns()->getValue(),
            'teamfight_participation' => $match->getTeamfightParticipation()->getValue(),

            'side' => $match->getSide()->value,
            'win' => (int)$match->isWin(),

            'lane' => $match->getRole()->toInt(),
            'role' => $match->getRole()->toInt(),

            'start_time' => $match->getStartTime()->format('Y-m-d H:i:s'),
        ];
    }
}