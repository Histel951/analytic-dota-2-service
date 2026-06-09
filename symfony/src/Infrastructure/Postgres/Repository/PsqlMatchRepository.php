<?php

namespace App\Infrastructure\Postgres\Repository;

use App\Domain\Match\Entity\MatchEntity;
use App\Domain\Match\Entity\MatchPlayers;
use App\Domain\Match\Entity\MatchProcessing;
use App\Domain\Match\Repository\MatchRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final readonly class PsqlMatchRepository implements MatchRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    private const MATCH_TABLE = 'match';
    private const MATCH_PLAYERS_TABLE = 'match_players';
    private const MATCH_PROCESSING_TABLE = 'match_processing';

    /**
     * @throws Exception
     */
    public function save(MatchEntity $entity): void
    {
        $this->connection->insert(self::MATCH_TABLE, [
            'match_id' => $entity->getMatchId()->getValue(),
            'start_time' => $entity->getStartTime()->getTimestamp(),
            'duration' => $entity->getDuration(),
            'radiant_win' => $entity->isRadiantWin(),
        ]);
    }

    /**
     * @throws Exception
     */
    public function saveProccessingStatus(MatchProcessing $entity): void
    {
        $this->connection->insert(self::MATCH_PLAYERS_TABLE, [
            'match_id' => $entity->getMatchId()->getValue(),
            'fetched_at' => $entity->getFetchedAt()->getTimestamp(),
            'analytics_processed_at' => $entity->getAnalyticsProcessedAt()->getTimestamp(),
            'fantasy_processed_at' => $entity->getFantasyProcessedAt()->getTimestamp(),
            'status' => $entity->getStatus()->value,
            'parsed_at' => $entity->getParsedAt()->getTimestamp(),
        ]);
    }

    /**
     * @throws Exception
     */
    public function saveMatchPlayer(MatchPlayers $entity): void
    {
        $this->connection->insert(self::MATCH_PROCESSING_TABLE, [
            'match_id' => $entity->getMatchId()->getValue(),
            'player_id' => $entity->getPlayerId()->getValue(),
            'player_pro_name' => $entity->getPlayerProName(),
        ]);
    }
}