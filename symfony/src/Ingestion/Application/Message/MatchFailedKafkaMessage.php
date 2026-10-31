<?php
declare(strict_types=1);

namespace App\Ingestion\Application\Message;

final readonly class MatchFailedKafkaMessage
{
    public function __construct(
        private int $matchId,
        private string $errorType,
        private string $errorMessage,
        private int $timestamp,
    ) {
    }

    public function getMatchId(): int
    {
        return $this->matchId;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function toArray(): array
    {
        return [
            'match_id' => $this->matchId,
            'error_type' => $this->errorType,
            'error_message' => $this->errorMessage,
            'timestamp' => $this->timestamp,
        ];
    }
}
