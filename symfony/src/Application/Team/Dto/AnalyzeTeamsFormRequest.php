<?php
declare(strict_types=1);

namespace App\Application\Team\Dto;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AnalyzeTeamsFormRequest
{
    /**
     * @param int[] $teamIds
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Count(min: 1)]
        #[Assert\All([
            new Assert\Type('integer'),
            new Assert\Positive()
        ])]
        public array $teamIds,

        #[Assert\Type(DateTimeImmutable::class)]
        public ?DateTimeImmutable $dateFrom = null,

        #[Assert\Type(DateTimeImmutable::class)]
        public ?DateTimeImmutable $dateTo = null,
    ) {}
}
