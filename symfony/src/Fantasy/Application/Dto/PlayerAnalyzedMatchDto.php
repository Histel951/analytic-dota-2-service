<?php
declare(strict_types=1);

namespace App\Fantasy\Application\Dto;

final readonly class PlayerAnalyzedMatchDto
{
    public function __construct(
        private int $matchId,
        private int $matchDuration,
        private string $startTime,
        private PlayerMatchStatisticDto $statistic,
        private bool $isWin,
    ) {}

    public function toArray(): array
    {
        return [
            'match_id' => $this->matchId,
            'match_duration' => $this->matchDuration,
            'start_time' => $this->startTime,
            'statistic' => $this->statistic->toArray(),
            'is_win' => $this->isWin,
        ];
    }
}
