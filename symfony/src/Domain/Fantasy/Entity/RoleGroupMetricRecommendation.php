<?php
declare(strict_types=1);

namespace App\Domain\Fantasy\Entity;

use App\Domain\Fantasy\Enum\RoleGroup;
use App\Domain\Fantasy\ValueObject\MetricContribution;

final readonly class RoleGroupMetricRecommendation
{
    /**
     * @param RoleGroup $roleGroup
     * @param MetricContribution[] $topMetrics
     * @param int $matchesAnalyzed
     * @param int $playersAnalyzed
     */
    public function __construct(
        private RoleGroup $roleGroup,
        private array $topMetrics,
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

    public function getMatchesAnalyzed(): int
    {
        return $this->matchesAnalyzed;
    }

    public function getPlayersAnalyzed(): int
    {
        return $this->playersAnalyzed;
    }
}
