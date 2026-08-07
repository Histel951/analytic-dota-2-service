<?php
declare(strict_types=1);

namespace App\Application\Fantasy\Dto;

final readonly class TopComboDto
{
    /**
     * @param MetricValueDto[] $topMetrics
     * @param PlayerInComboDto[] $players
     */
    public function __construct(
        private int $teamId,
        private array $topMetrics,
        private array $players,
        private float $avgCombinedScore,
        private int $matchesCount,
    ) {}

    public function toArray(): array
    {
        return [
            'team_id' => $this->teamId,
            'top_metrics' => array_map(fn($m) => $m->toArray(), $this->topMetrics),
            'players' => array_map(fn($p) => $p->toArray(), $this->players),
            'avg_combined_score' => round($this->avgCombinedScore, 2),
            'matches_count' => $this->matchesCount,
        ];
    }
}
