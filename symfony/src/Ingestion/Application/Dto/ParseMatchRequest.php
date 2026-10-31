<?php
declare(strict_types=1);

namespace App\Ingestion\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ParseMatchRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Match ID is required')]
        #[Assert\Positive(message: 'Match ID must be positive')]
        #[Assert\Type(type: 'integer', message: 'Match ID must be an integer')]
        public int $matchId,
    ) {}
}
