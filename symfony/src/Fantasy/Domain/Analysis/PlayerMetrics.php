<?php
declare(strict_types=1);

namespace App\Fantasy\Domain\Analysis;

final readonly class PlayerMetrics
{
    public function __construct(
        private string $metricName,
        private float $avgValue,
        private bool $isTopMetric,
    ) {}

    public function getMetricName(): string
    {
        return $this->metricName;
    }

    public function getAvgValue(): float
    {
        return $this->avgValue;
    }

    public function isTopMetric(): bool
    {
        return $this->isTopMetric;
    }
}
