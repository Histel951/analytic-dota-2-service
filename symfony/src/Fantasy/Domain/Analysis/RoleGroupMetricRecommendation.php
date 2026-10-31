<?php
declare(strict_types=1);

namespace App\Fantasy\Domain\Analysis;


final readonly class RoleGroupMetricRecommendation
{
    /**
     * @param RoleGroup $roleGroup
     * @param MetricContribution[] $topMetrics
     * @param TopComboRecommendation[] $topCombos
     * @param int $matchesAnalyzed
     * @param int $playersAnalyzed
     */
    public function __construct(
        private RoleGroup $roleGroup,
        private array $topMetrics,
        private array $topCombos,
        private int $matchesAnalyzed,
        private int $playersAnalyzed,
    ) {
        foreach ($topMetrics as $metric) {
            if (!$metric instanceof MetricContribution) {
                throw new \InvalidArgumentException(
                    "All top metrics must be instances of MetricContribution"
                );
            }
        }

        foreach ($topCombos as $combo) {
            if (!$combo instanceof TopComboRecommendation) {
                throw new \InvalidArgumentException(
                    "All top combos must be instances of TopComboRecommendation"
                );
            }
        }
    }

    public function getRoleGroup(): RoleGroup
    {
        return $this->roleGroup;
    }

    /**
     * @return MetricContribution[]
     */
    public function getTopMetrics(): array
    {
        return $this->topMetrics;
    }

    /**
     * @return TopComboRecommendation[]
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
}
