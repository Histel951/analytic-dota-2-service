<?php
declare(strict_types=1);

namespace App\Application\Team\UseCase;

use App\Application\Team\Service\FetchTeamMatchesService;

final readonly class FetchTeamMatchesUseCase
{
    public function __construct(
        private FetchTeamMatchesService $service,
    ) {}

    public function execute(
        int $teamId,
        ?int $limit = null,
        ?\DateTimeImmutable $dateFrom = null,
        ?\DateTimeImmutable $dateTo = null
    ): int {
        return $this->service->fetchAndDispatchMatches(
            $teamId,
            $limit,
            $dateFrom,
            $dateTo
        );
    }
}
