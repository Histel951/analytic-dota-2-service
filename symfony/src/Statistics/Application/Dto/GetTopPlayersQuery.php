<?php
declare(strict_types=1);

namespace App\Statistics\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GetTopPlayersQuery
{
    public function __construct(
        #[Assert\NotBlank(message: 'Metric parameter is required')]
        #[Assert\Choice(
            choices: [
                'gpm', 'xpm', 'net_worth', 'total_gold', 'total_xp',
                'kills', 'deaths', 'assists', 'hero_damage', 'tower_damage',
                'hero_healing', 'creeps_last_hits', 'creeps_denies',
                'observers_placed', 'teamfight_participation'
            ],
            message: 'Invalid metric. Supported metrics: {{ choices }}'
        )]
        public string $metric,

        #[Assert\Positive(message: 'Limit must be positive')]
        #[Assert\LessThanOrEqual(value: 200, message: 'Limit must not exceed 200')]
        public int $limit = 50,

        #[Assert\PositiveOrZero(message: 'Offset must be zero or positive')]
        public int $offset = 0,
    ) {}
}
