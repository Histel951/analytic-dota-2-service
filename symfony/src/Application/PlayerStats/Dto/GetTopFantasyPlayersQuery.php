<?php
declare(strict_types=1);

namespace App\Application\PlayerStats\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GetTopFantasyPlayersQuery
{
    public function __construct(
        #[Assert\Positive(message: 'Match ID must be positive')]
        public ?int $matchId = null,

        #[Assert\Positive(message: 'Limit must be positive')]
        #[Assert\LessThanOrEqual(value: 100, message: 'Limit must not exceed 100')]
        public int $limit = 10,

        #[Assert\Positive(message: 'Matches count must be positive')]
        #[Assert\LessThanOrEqual(value: 100, message: 'Matches count must not exceed 100')]
        public int $matches = 20,

        #[Assert\PositiveOrZero(message: 'Offset must be zero or positive')]
        public int $offset = 0,
    ) {}
}
