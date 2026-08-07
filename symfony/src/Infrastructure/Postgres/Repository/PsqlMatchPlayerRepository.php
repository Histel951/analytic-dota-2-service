<?php
declare(strict_types=1);

namespace App\Infrastructure\Postgres\Repository;

use App\Domain\Match\Entity\MatchPlayers;
use App\Domain\Match\Repository\MatchPlayersRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final readonly class PsqlMatchPlayerRepository implements MatchPlayersRepository
{
    private const TABLE = 'match_players';

    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @throws Exception
     */
    public function saveMatchPlayer(MatchPlayers $entity): void
    {
        $this->connection->insert(self::TABLE, [
            'match_id' => $entity->getMatchId()->getValue(),
            'player_id' => $entity->getPlayerId()->getValue(),
            'player_pro_name' => $entity->getPlayerProName(),
        ]);
    }

    /**
     * @param MatchPlayers[] $players
     * @return void
     * @throws Exception
     */
    public function saveMatchPlayersBulk(array $players): void
    {
        if (empty($players)) {
            return;
        }

        $values = [];
        $parameters = [];

        foreach ($players as $player) {
            $values[] = "(?, ?, ?)";
            $parameters[] = $player->getMatchId()->getValue();
            $parameters[] = $player->getPlayerId()->getValue();
            $parameters[] = $player->getPlayerProName();
        }

        $sql = "INSERT INTO " . self::TABLE . " 
                (match_id, player_id, player_pro_name) 
                VALUES " . implode(', ', $values) . "
                ON CONFLICT (match_id, player_id) DO NOTHING";

        $this->connection->executeStatement($sql, $parameters);
    }

    /**
     * @throws Exception
     */
    public function getNicknamesByIds(array $playerIds): array
    {
        if (empty($playerIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
        
        $sql = "
            SELECT DISTINCT player_id, player_pro_name
            FROM " . self::TABLE . "
            WHERE player_id IN ({$placeholders})
              AND player_pro_name IS NOT NULL
        ";

        $rows = $this->connection->fetchAllAssociative($sql, $playerIds);

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['player_id']] = $row['player_pro_name'] ?? 'Unknown';
        }

        return $result;
    }
}