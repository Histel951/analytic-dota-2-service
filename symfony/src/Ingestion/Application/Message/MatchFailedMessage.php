<?php
declare(strict_types=1);

namespace App\Ingestion\Application\Message;

final readonly class MatchFailedMessage
{
    public function __construct(
        private int $matchId,
        private string $errorMessage,
        private string $errorType,
    ) {
    }

    public function getMatchId(): int
    {
        return $this->matchId;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }
}
