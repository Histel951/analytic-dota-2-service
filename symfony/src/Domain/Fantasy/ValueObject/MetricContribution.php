<?php
declare(strict_types=1);

namespace App\Domain\Fantasy\ValueObject;

final readonly class MetricContribution
{
    public function __construct(
        private string $metricName,
        private float $avgValue,
        private float $contributionPercent,
    ) {
        if ($contributionPercent < 0 || $contributionPercent > 100) {
            throw new \InvalidArgumentException(
                "Contribution percent must be between 0 and 100, got: {$contributionPercent}"
            );
        }
    }

    public function getMetricName(): string
    {
        return $this->metricName;
    }

    public function getAvgValue(): float
    {
        return $this->avgValue;
    }

    public function getContributionPercent(): float
    {
        return $this->contributionPercent;
    }
}
