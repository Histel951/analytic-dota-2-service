<?php
declare(strict_types=1);

namespace App\Application\PlayerStats\UseCase;

use App\Application\PlayerStats\Service\GetTopFantasyPlayersService;

final readonly class GetTopFantasyPlayersUseCase
{
    public function __construct(
        private GetTopFantasyPlayersService $service,
    ) {}

    public function execute(int $limit, int $matches, int $offset = 0): array
    {
        return array_map(
            fn($player) => $player->toArray(),
            $this->service->getTopFantasyPlayers($limit, $matches, $offset)
        );
    }
}
