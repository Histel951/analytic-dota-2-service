<?php
declare(strict_types=1);

namespace App\Application\Fantasy\Dto;

final readonly class RoleGroupMetricsDto
{
    /**
     * @param string $group
     * @param string $groupLabel
     * @param int[] $roles
     * @param MetricDto[] $topMetrics
     * @param int $matchesAnalyzed
     * @param int $playersAnalyzed
     */
    public function __construct(
        private string $group,
        private string $groupLabel,
        private array $roles,
        private array $topMetrics,
        private int $matchesAnalyzed,
        private int $playersAnalyzed,
    ) {}

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getGroupLabel(): string
    {
        return $this->groupLabel;
    }

    /**
     * @return int[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @return MetricDto[]
     */
    public function getTopMetrics(): array
    {
        return $this->topMetrics;
    }

    public function getMatchesAnalyzed(): int
    {
        return $this->matchesAnalyzed;
    }

    public function getPlayersAnalyzed(): int
    {
        return $this->playersAnalyzed;
    }

    public function toArray(): array
    {
        return [
            'group' => $this->group,
            'group_label' => $this->groupLabel,
            'roles' => $this->roles,
            'top_metrics' => array_map(fn($metric) => $metric->toArray(), $this->topMetrics),
            'matches_analyzed' => $this->matchesAnalyzed,
            'players_analyzed' => $this->playersAnalyzed,
        ];
    }
}
