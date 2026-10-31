<?php
declare(strict_types = 1);

namespace App\Ingestion\Application\Message;

final readonly class ParseMatchMessage
{
    public function __construct(
        private int $matchId,
    )
    {
    }

    public function getMatchId(): int
    {
        return $this->matchId;
    }
}