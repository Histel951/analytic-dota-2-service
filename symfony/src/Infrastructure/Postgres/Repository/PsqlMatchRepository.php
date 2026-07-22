<?php

namespace App\Infrastructure\Postgres\Repository;

use App\Domain\Match\Entity\MatchEntity;
use App\Domain\Match\Repository\MatchRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;

final readonly class PsqlMatchRepository implements MatchRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    private const TABLE = 'matches';

    /**
     * @throws Exception
     */
    public function save(MatchEntity $entity): void
    {
        $sql = "INSERT INTO " . self::TABLE . " 
                (match_id, start_time, duration, radiant_win) 
                VALUES (?, ?, ?, ?) 
                ON CONFLICT (match_id) DO NOTHING";
        
        $this->connection->executeStatement(
            $sql,
            [
                $entity->getMatchId()->getValue(),
                $entity->getStartTime()->format('Y-m-d H:i:s'),
                $entity->getDuration(),
                $entity->isRadiantWin(),
            ],
            [
                Types::BIGINT,
                Types::STRING,
                Types::INTEGER,
                Types::BOOLEAN,
            ]
        );
    }
}