<?php
declare(strict_types=1);

namespace App\Ingestion\Domain\Processing;

use DateTimeImmutable;
use App\Shared\Domain\MatchId;

final class MatchProcessing
{
    public function __construct(
        private readonly MatchId           $matchId,
        private readonly DateTimeImmutable $parsedAt,
        private ?DateTimeImmutable         $fetchedAt = null,
        private ?DateTimeImmutable         $analyticsProcessedAt = null,
        private ?DateTimeImmutable         $fantasyProcessedAt = null,
        private ?MatchProcessingStatus     $status = null,
        private ?DateTimeImmutable         $createdAt = null,
    ) {
        $this->status = $status ?? MatchProcessingStatus::PENDING;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getMatchId(): MatchId
    {
        return $this->matchId;
    }

    public function getFetchedAt(): ?DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    public function getAnalyticsProcessedAt(): ?DateTimeImmutable
    {
        return $this->analyticsProcessedAt;
    }

    public function getFantasyProcessedAt(): ?DateTimeImmutable
    {
        return $this->fantasyProcessedAt;
    }

    public function getStatus(): MatchProcessingStatus
    {
        return $this->status;
    }

    public function getParsedAt(): DateTimeImmutable
    {
        return $this->parsedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function markAsFetched(): self
    {
        $this->fetchedAt = new DateTimeImmutable();
        $this->status = MatchProcessingStatus::FETCHED;

        return $this;
    }

    public function markAnalyticsProcessed(): self
    {
        $this->analyticsProcessedAt = new DateTimeImmutable();
        $this->status = MatchProcessingStatus::ANALYTICS_COMPLETED;

        return $this;
    }

    public function markProcessingFantasy(): self
    {
        $this->status = MatchProcessingStatus::PROCESSING_FANTASY;

        return $this;
    }

    public function markFantasyProcessed(): self
    {
        $this->fantasyProcessedAt = new DateTimeImmutable();
        $this->status = MatchProcessingStatus::FANTASY_COMPLETED;

        return $this;
    }

    public function markAsCompleted(): self
    {
        $this->status = MatchProcessingStatus::COMPLETED;

        return $this;
    }

    public function markAsFailed(): self
    {
        $this->status = MatchProcessingStatus::FAILED;

        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->status === MatchProcessingStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === MatchProcessingStatus::FAILED;
    }
}