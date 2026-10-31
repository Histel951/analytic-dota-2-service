<?php
declare(strict_types=1);

namespace App\Ingestion\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class FetchTeamMatchesRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Team ID is required')]
        #[Assert\Positive(message: 'Team ID must be positive')]
        #[Assert\Type(type: 'integer', message: 'Team ID must be an integer')]
        public int $teamId,

        #[Assert\Positive(message: 'Limit must be positive')]
        public ?int $limit = null,

        public ?\DateTimeImmutable $dateFrom = null,

        public ?\DateTimeImmutable $dateTo = null,
    ) {}
}
