<?php
declare(strict_types=1);

namespace App\Application\PlayerStats\UseCase;

use App\Application\PlayerStats\Service\GetTopPlayersByMetricService;

final readonly class GetTopPlayersByMetricUseCase
{
    public function __construct(
        private GetTopPlayersByMetricService $service,
    ) {}

    public function execute(string $metric, int $limit, int $offset): array
    {
        $topPlayers = $this->service->getTopPlayers($metric, $limit, $offset);

        return array_map(
            fn($dto) => $dto->toArray(),
            $topPlayers
        );
    }
}
