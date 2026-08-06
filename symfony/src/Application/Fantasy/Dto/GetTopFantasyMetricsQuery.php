<?php
declare(strict_types=1);

namespace App\Application\Fantasy\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GetTopFantasyMetricsQuery
{
    /**
     * @param int[]|null $teamIds
     * @param int[]|null $playerIds
     */
    public function __construct(
        #[Assert\All([
            new Assert\Positive(message: 'Each team ID must be positive')
        ])]
        public ?array $teamIds = null,

        #[Assert\All([
            new Assert\Positive(message: 'Each player ID must be positive')
        ])]
        public ?array $playerIds = null,

        #[Assert\Positive(message: 'Minimum matches per player must be positive')]
        public int $minMatchesPerPlayer = 10,

        #[Assert\DateTime(message: 'Invalid date format for date_from')]
        public ?string $dateFrom = null,

        #[Assert\DateTime(message: 'Invalid date format for date_to')]
        public ?string $dateTo = null,

        #[Assert\Positive(message: 'Matches limit must be positive')]
        public ?int $matchesLimit = null,
    ) {}
}
