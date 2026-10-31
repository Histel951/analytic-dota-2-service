<?php
declare(strict_types=1);

namespace App\Fantasy\Application\Dto;

use App\Fantasy\Domain\Analysis\EmblemType;

final readonly class MetricDto
{
    public function __construct(
        private string $metricName,
        private float $avgValue,
        private float $contributionPercent,
    ) {}

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

    public function toArray(): array
    {
        $emblem = EmblemType::fromMetric($this->metricName);

        return [
            'metric' => $this->metricName,
            'avg_value' => round($this->avgValue, 2),
            'contribution_percent' => $this->contributionPercent,
            'type' => $emblem->value,
            'name' => $emblem->getLabel(),
        ];
    }
}
