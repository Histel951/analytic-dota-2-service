<?php
declare(strict_types=1);

namespace App\Fantasy\Application\Dto;

final readonly class RoleGroupMetricsDto
{
    /**
     * @param string $group
     * @param string $groupLabel
     * @param int[] $roles
     * @param MetricDto[] $topMetrics
     * @param TopComboDto[] $topCombos
     * @param int $matchesAnalyzed
     * @param int $playersAnalyzed
     */
    public function __construct(
        private string $group,
        private string $groupLabel,
        private array $roles,
        private array $topMetrics,
        private array $topCombos,
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

    /**
     * @return TopComboDto[]
     */
    public function getTopCombos(): array
    {
        return $this->topCombos;
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
            'top_combos' => array_map(fn($combo) => $combo->toArray(), $this->topCombos),
            'matches_analyzed' => $this->matchesAnalyzed,
            'players_analyzed' => $this->playersAnalyzed,
        ];
    }
}
