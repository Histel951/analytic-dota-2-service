<?php
declare(strict_types=1);

namespace App\Infrastructure\Postgres\Repository;

use App\Domain\Team\Entity\TeamMatch;
use App\Domain\Team\Repository\TeamMatchRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;

final readonly class PsqlTeamMatchRepository implements TeamMatchRepository
{
    private const TABLE = 'team_matches';

    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @throws Exception
     */
    public function save(TeamMatch $teamMatch): void
    {
        $sql = "INSERT INTO " . self::TABLE . " 
                (team_id, match_id, is_win) 
                VALUES (?, ?, ?) 
                ON CONFLICT (team_id, match_id) DO UPDATE SET 
                    is_win = EXCLUDED.is_win";

        $this->connection->executeStatement(
            $sql,
            [
                $teamMatch->getTeamId()->getValue(),
                $teamMatch->getMatchId()->getValue(),
                $teamMatch->isWin(),
            ],
            [
                Types::BIGINT,
                Types::BIGINT,
                Types::BOOLEAN,
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function saveBulk(array $teamMatches): void
    {
        if (empty($teamMatches)) {
            return;
        }

        $this->connection->beginTransaction();

        try {
            foreach ($teamMatches as $teamMatch) {
                $this->save($teamMatch);
            }

            $this->connection->commit();
        } catch (Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function findByTeamId(
        TeamId $teamId,
        ?DateTimeImmutable $dateFrom = null,
        ?DateTimeImmutable $dateTo = null
    ): array {
        $sql = "SELECT tm.team_id, tm.match_id, tm.is_win, tm.created_at, m.start_time
                FROM " . self::TABLE . " tm
                INNER JOIN matches m ON tm.match_id = m.match_id
                WHERE tm.team_id = ?";

        $params = [$teamId->getValue()];
        $types = [Types::BIGINT];

        if ($dateFrom !== null) {
            $sql .= " AND m.start_time >= ?";
            $params[] = $dateFrom->format('Y-m-d H:i:s');
            $types[] = Types::STRING;
        }

        if ($dateTo !== null) {
            $sql .= " AND m.start_time <= ?";
            $params[] = $dateTo->format('Y-m-d H:i:s');
            $types[] = Types::STRING;
        }

        $sql .= " ORDER BY m.start_time DESC";

        $result = $this->connection->executeQuery($sql, $params, $types);
        $rows = $result->fetchAllAssociative();

        $teamMatches = [];
        foreach ($rows as $row) {
            $teamMatches[] = new TeamMatch(
                teamId: new TeamId((int)$row['team_id']),
                matchId: new MatchId((int)$row['match_id']),
                isWin: (bool)$row['is_win'],
                createdAt: new DateTimeImmutable($row['created_at']),
            );
        }

        return $teamMatches;
    }

    /**
     * @throws Exception
     */
    public function exists(TeamId $teamId, MatchId $matchId): bool
    {
        $sql = "SELECT COUNT(*) FROM " . self::TABLE . " 
                WHERE team_id = ? AND match_id = ?";

        $result = $this->connection->executeQuery(
            $sql,
            [$teamId->getValue(), $matchId->getValue()],
            [Types::BIGINT, Types::BIGINT]
        );

        return $result->fetchOne() > 0;
    }
}
