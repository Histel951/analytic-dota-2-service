<?php
declare(strict_types=1);

namespace App\Infrastructure\Postgres\Repository;

use Doctrine\DBAL\Connection;

final readonly class PsqlPlayerRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function savePlayer(
        int $playerId,
        string $nickname,
    ): void {
        $this->connection->insert('players', [
            'player_id' => $playerId,
            'nickname' => $nickname,
        ]);
    }
}