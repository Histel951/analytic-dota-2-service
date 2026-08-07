<?php
declare(strict_types=1);

namespace App\Application\Fantasy\Dto;

final readonly class PlayerInComboDto
{
    /**
     * @param MetricValueDto[] $allMetrics
     * @param MetricValueDto[] $topMetrics
     */
    public function __construct(
        private int $playerId,
        private string $nickname,
        private int $role,
        private float $avgTotalScore,
        private array $allMetrics,
        private array $topMetrics,
        private int $matchesCount,
    ) {}

    public function toArray(): array
    {
        return [
            'player_id' => $this->playerId,
            'nickname' => $this->nickname,
            'role' => $this->role,
            'avg_total_score' => round($this->avgTotalScore, 2),
            'top_metrics' => array_map(fn($m) => $m->toArray(), $this->topMetrics),
            'all_metrics' => array_map(fn($m) => $m->toArray(), $this->allMetrics),
            'matches_count' => $this->matchesCount,
        ];
    }
}
