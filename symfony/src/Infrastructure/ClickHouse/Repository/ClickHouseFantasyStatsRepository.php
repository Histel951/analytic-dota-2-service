<?php
declare(strict_types=1);

namespace App\Infrastructure\ClickHouse\Repository;

use App\Domain\Match\Dto\FantasyScoreResult;
use App\Domain\Match\Repository\FantasyStatsReadRepository;
use App\Domain\Match\Repository\FantasyStatsRepository;
use ClickHouseDB\Client;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerId;

final readonly class ClickHouseFantasyStatsRepository implements FantasyStatsRepository, FantasyStatsReadRepository
{
    private const TABLE_NAME = 'player_match_fantasy_stats';
    
    private const COLUMNS = [
        'match_id',
        'player_id',
        'kills_score',
        'deaths_score',
        'creeps_score',
        'gpm_score',
        'tower_score',
        'observer_score',
        'stacks_score',
        'rune_score',
        'roshan_score',
        'courier_score',
        'stun_score',
        'first_blood_score',
        'teamfight_score',
        'tormentor_score',
        'smoke_score',
        'watcher_score',
        'total_score',
        'version',
    ];

    private const VERSION = 1;

    public function __construct(
        private Client $clickhouse,
    ) {
    }

    public function save(
        MatchId $matchId,
        PlayerId $playerId,
        FantasyScoreResult $score
    ): void {
        $this->clickhouse->insert(
            self::TABLE_NAME,
            [
                $this->mapToRow($matchId, $playerId, $score)
            ],
            self::COLUMNS
        );
    }

    public function saveBulk(array $fantasyStats): void
    {
        $rows = [];
        foreach ($fantasyStats as $stat) {
            $rows[] = $this->mapToRow(
                $stat['matchId'],
                $stat['playerId'],
                $stat['score']
            );
        }

        $this->clickhouse->insert(
            self::TABLE_NAME,
            $rows,
            self::COLUMNS
        );
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function findTopFantasyPlayers(int $limit, int $matches, int $offset): array
    {
        $sql = sprintf(
            'SELECT
                player_id,
                arraySlice(groupArray(match_id), 1, %d) AS matches,
                avg(total_score) AS average_total_score,
                avg(kills_score) AS kills_score,
                avg(gpm_score) AS gpm_score,
                avg(roshan_score) AS roshan_score,
                avg(tower_score) AS tower_score,
                avg(observer_score) AS observer_score,
                avg(stacks_score) AS stacks_score,
                avg(rune_score) AS rune_score,
                avg(courier_score) AS courier_score,
                avg(stun_score) AS stun_score,
                avg(first_blood_score) AS first_blood_score,
                avg(teamfight_score) AS teamfight_score,
                avg(tormentor_score) AS tormentor_score,
                avg(smoke_score) AS smoke_score,
                avg(watcher_score) AS watcher_score
            FROM (
                SELECT *
                FROM player_match_fantasy_stats
                ORDER BY player_id, calculated_at DESC
                LIMIT %d BY player_id
            )
            GROUP BY player_id
            HAVING length(matches) = %d
            ORDER BY average_total_score DESC
            LIMIT %d OFFSET %d',
            $matches,
            $matches,
            $matches,
            $limit,
            $offset
        );

        $statement = $this->clickhouse->select($sql);
        $rows = $statement->rows();

        return array_map(
            function (array $row): array {
                foreach (['matches'] as $field) {
                    if (array_key_exists($field, $row) && is_string($row[$field])) {
                        $row[$field] = json_decode($row[$field], true) ?: [];
                    }
                }

                return $row;
            },
            $rows
        );
    }

    private function mapToRow(
        MatchId $matchId,
        PlayerId $playerId,
        FantasyScoreResult $score
    ): array {
        return [
            'match_id' => $matchId->getValue(),
            'player_id' => $playerId->getValue(),
            'kills_score' => (int)$score->killsScore,
            'deaths_score' => (int)$score->deathsScore,
            'creeps_score' => (int)$score->creepsScore,
            'gpm_score' => (int)$score->gpmScore,
            'tower_score' => (int)$score->towerScore,
            'observer_score' => (int)$score->observerScore,
            'stacks_score' => (int)$score->stacksScore,
            'rune_score' => (int)$score->runeScore,
            'roshan_score' => (int)$score->roshanScore,
            'courier_score' => (int)$score->courierScore,
            'stun_score' => $score->stunScore,
            'first_blood_score' => (int)$score->firstBloodScore,
            'teamfight_score' => $score->teamfightScore,
            'tormentor_score' => (int)$score->tormentorScore,
            'smoke_score' => (int)$score->smokeScore,
            'watcher_score' => (int)$score->watcherScore,
            'total_score' => $score->totalScore,
            'version' => self::VERSION,
        ];
    }
}
