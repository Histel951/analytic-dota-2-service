<?php
declare(strict_types=1);

namespace App\Application\Fantasy\Dto;

final readonly class MetricValueDto
{
    public function __construct(
        private string $metricName,
        private float $avgValue,
    ) {}

    public function toArray(): array
    {
        return [
            'metric' => $this->metricName,
            'avg_value' => round($this->avgValue, 2),
        ];
    }
}
