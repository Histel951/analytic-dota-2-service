<?php
declare(strict_types=1);

namespace App\Fantasy\Domain\Port;

use App\Fantasy\Domain\Analysis\RoleGroupMetricRecommendation;
use DateTimeImmutable;
use App\Shared\Domain\PlayerId;
use App\Shared\Domain\TeamId;

interface FantasyMetricAnalysisRepository
{
    /**
     * @param TeamId[]|null $teamIds
     * @param PlayerId[]|null $playerIds
     * @param int $minMatchesPerPlayer
     * @param DateTimeImmutable|null $dateFrom
     * @param DateTimeImmutable|null $dateTo
     * @param int|null $matchesLimit
     * @return RoleGroupMetricRecommendation[]
     */
    public function getTopMetricsByRoleGroups(
        ?array $teamIds,
        ?array $playerIds,
        int $minMatchesPerPlayer,
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo,
        ?int $matchesLimit,
    ): array;

    /**
     * Возвращает список ID игроков, прошедших фильтры
     * 
     * @param TeamId[]|null $teamIds
     * @param PlayerId[]|null $playerIds
     * @param int $minMatchesPerPlayer
     * @param DateTimeImmutable|null $dateFrom
     * @param DateTimeImmutable|null $dateTo
     * @param int|null $matchesLimit
     * @return PlayerId[]
     */
    public function getEligiblePlayerIds(
        ?array $teamIds,
        ?array $playerIds,
        int $minMatchesPerPlayer,
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo,
        ?int $matchesLimit,
    ): array;

    /**
     * Возвращает список уникальных ID команд для игроков, прошедших фильтры
     * 
     * @param TeamId[]|null $teamIds
     * @param PlayerId[]|null $playerIds
     * @param int $minMatchesPerPlayer
     * @param DateTimeImmutable|null $dateFrom
     * @param DateTimeImmutable|null $dateTo
     * @param int|null $matchesLimit
     * @return TeamId[]
     */
    public function getEligibleTeamIds(
        ?array $teamIds,
        ?array $playerIds,
        int $minMatchesPerPlayer,
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo,
        ?int $matchesLimit,
    ): array;
}
