<?php
declare(strict_types=1);

namespace App\Domain\Match\Repository;

use App\Domain\Match\Dto\FantasyScoreResult;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerId;

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
