<?php
declare(strict_types=1);

namespace App\Fantasy\Application\Dto;

use App\Fantasy\Domain\Analysis\EmblemType;

final readonly class MetricValueDto
{
    public function __construct(
        private string $metricName,
        private float $avgValue,
    ) {}

    public function toArray(): array
    {
        $emblem = EmblemType::fromMetric($this->metricName);

        return [
            'metric' => $this->metricName,
            'avg_value' => round($this->avgValue, 2),
            'type' => $emblem->value,
            'name' => $emblem->getLabel(),
        ];
    }
}
