<?php
declare(strict_types=1);

namespace App\Application\Match\Message;

use App\Domain\Match\Entity\ImportedMatch;

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
