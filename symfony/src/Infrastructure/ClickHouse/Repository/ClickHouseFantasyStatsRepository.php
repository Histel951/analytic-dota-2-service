<?php
declare(strict_types=1);

namespace App\Infrastructure\ClickHouse\Repository;

use App\Domain\Match\Dto\FantasyScoreResult;
use App\Domain\Match\Repository\FantasyStatsRepository;
use ClickHouseDB\Client;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerId;

final readonly class ClickHouseFantasyStatsRepository implements FantasyStatsRepository
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
