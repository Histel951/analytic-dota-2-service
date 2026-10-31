<?php
declare(strict_types=1);

namespace App\Domain\Match\Repository;

use App\Domain\Match\Dto\FantasyScoreResult;
use App\Shared\Domain\MatchId;
use App\Shared\Domain\PlayerId;

interface FantasyStatsRepository
{
    public function save(
        MatchId $matchId,
        PlayerId $playerId,
        FantasyScoreResult $score
    ): void;

    /**
     * @param array<array{matchId: MatchId, playerId: PlayerId, score: FantasyScoreResult}> $fantasyStats
     */
    public function saveBulk(array $fantasyStats): void;
}
