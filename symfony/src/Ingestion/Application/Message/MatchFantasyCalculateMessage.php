<?php
declare(strict_types=1);

namespace App\Ingestion\Application\Message;

use App\Ingestion\Domain\Match\ImportedMatch;

final readonly class MatchFantasyCalculateMessage
{
    public function __construct(
        private int $matchId,
        private string $serializedMatch,
    ) {
    }

    public function getMatchId(): int
    {
        return $this->matchId;
    }

    public function getSerializedMatch(): string
    {
        return $this->serializedMatch;
    }

    public function getMatch(): ImportedMatch
    {
        return unserialize($this->serializedMatch);
    }
}
