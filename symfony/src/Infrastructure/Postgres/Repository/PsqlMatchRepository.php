<?php

namespace App\Infrastructure\Postgres\Repository;

use App\Domain\Match\Entity\MatchEntity;
use App\Domain\Match\Entity\MatchProcessing;
use App\Domain\Match\Repository\MatchRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

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
        $this->connection->insert(self::TABLE, [
            'match_id' => $entity->getMatchId()->getValue(),
            'start_time' => $entity->getStartTime()->format('Y-m-d H:i:s'),
            'duration' => $entity->getDuration(),
            'radiant_win' => $entity->isRadiantWin(),
        ]);
    }
}