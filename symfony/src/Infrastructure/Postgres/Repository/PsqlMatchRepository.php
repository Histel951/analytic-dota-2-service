<?php

namespace App\Infrastructure\Postgres\Repository;

use App\Domain\Match\Repository\MatchRepository;
use Doctrine\DBAL\Connection;

final readonly class PsqlMatchRepository implements MatchRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function insert(): void
    {
        $this->connection->insert('match', []);
    }
}