<?php
declare(strict_types=1);

namespace App\Domain\Match\Entity;

use DateTimeImmutable;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Region;

final class MatchEntity
{
    public function __construct(
        private readonly MatchId           $matchId,
        private readonly DateTimeImmutable $startTime,
        private readonly int               $duration,
        private readonly bool              $radiantWin,
        private readonly ?Region           $region = null,
        private ?DateTimeImmutable         $createdAt = null,
    )
    {
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getMatchId(): MatchId
    {
        return $this->matchId;
    }

    public function getStartTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function isRadiantWin(): bool
    {
        return $this->radiantWin;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRegion(): ?Region
    {
        return $this->region;
    }

    public function isLongMatch(int $thresholdSeconds = 3600): bool
    {
        return $this->duration > $thresholdSeconds;
    }
}