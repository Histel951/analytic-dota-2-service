<?php
declare(strict_types=1);

namespace App\Fantasy\Application\Dto;

final readonly class TopFantasyMetricsResult
{
    /**
     * @param int[]|null $teamIds
     * @param int[]|null $playerIds
     * @param int $teamCount
     * @param int $playerCount
     * @param RoleGroupMetricsDto[] $roleGroups
     */
    public function __construct(
        private ?array $teamIds,
        private ?array $playerIds,
        private int $teamCount,
        private int $playerCount,
        private array $roleGroups,
    ) {}

    public function getTeamIds(): ?array
    {
        return $this->teamIds;
    }

    public function getPlayerIds(): ?array
    {
        return $this->playerIds;
    }

    public function getTeamCount(): int
    {
        return $this->teamCount;
    }

    public function getPlayerCount(): int
    {
        return $this->playerCount;
    }

    /**
     * @return RoleGroupMetricsDto[]
     */
    public function getRoleGroups(): array
    {
        return $this->roleGroups;
    }

    public function toArray(): array
    {
        return [
            'team_ids' => $this->teamIds,
            'player_ids' => $this->playerIds,
            'team_count' => $this->teamCount,
            'player_count' => $this->playerCount,
            'role_groups' => array_map(fn($dto) => $dto->toArray(), $this->roleGroups),
        ];
    }
}
