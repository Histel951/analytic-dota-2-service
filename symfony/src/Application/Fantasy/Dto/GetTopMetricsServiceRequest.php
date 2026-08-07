<?php
declare(strict_types=1);

namespace App\Application\Fantasy\Dto;

use DateTimeImmutable;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;

final readonly class GetTopMetricsServiceRequest
{
    /**
     * @param TeamId[]|null $teamIds
     * @param PlayerId[]|null $playerIds
     * @param int $minMatchesPerPlayer
     * @param DateTimeImmutable|null $dateFrom
     * @param DateTimeImmutable|null $dateTo
     * @param int|null $matchesLimit
     */
    public function __construct(
        private ?array $teamIds,
        private ?array $playerIds,
        private int $minMatchesPerPlayer,
        private ?DateTimeImmutable $dateFrom,
        private ?DateTimeImmutable $dateTo,
        private ?int $matchesLimit,
    ) {}

    /**
     * @return TeamId[]|null
     */
    public function getTeamIds(): ?array
    {
        return $this->teamIds;
    }

    /**
     * @return PlayerId[]|null
     */
    public function getPlayerIds(): ?array
    {
        return $this->playerIds;
    }

    public function getMinMatchesPerPlayer(): int
    {
        return $this->minMatchesPerPlayer;
    }

    public function getDateFrom(): ?DateTimeImmutable
    {
        return $this->dateFrom;
    }

    public function getDateTo(): ?DateTimeImmutable
    {
        return $this->dateTo;
    }

    public function getMatchesLimit(): ?int
    {
        return $this->matchesLimit;
    }
}
