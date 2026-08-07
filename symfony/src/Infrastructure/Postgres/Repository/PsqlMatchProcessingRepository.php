<?php
declare(strict_types = 1);

namespace App\Infrastructure\Postgres\Repository;

use App\Domain\Match\Entity\MatchProcessing;
use App\Domain\Match\Enum\MatchProcessingStatus;
use App\Domain\Match\Repository\MatchProcessingRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;

final readonly class PsqlMatchProcessingRepository implements MatchProcessingRepository
{
    private const TABLE = 'match_processing';

    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @throws Exception
     */
    public function save(MatchProcessing $entity): void
    {
        $this->connection->insert(self::TABLE, [
            'match_id' => $entity->getMatchId()->getValue(),
            'fetched_at' => $entity->getFetchedAt()?->format('Y-m-d H:i:s'),
            'analytics_processed_at' => $entity->getAnalyticsProcessedAt()?->format('Y-m-d H:i:s'),
            'fantasy_processed_at' => $entity->getFantasyProcessedAt()?->format('Y-m-d H:i:s'),
            'status' => $entity->getStatus()->value,
            'parsed_at' => $entity->getParsedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @throws Exception
     */
    public function update(MatchProcessing $entity): void
    {
        $this->connection->update(
            self::TABLE,
            [
                'fetched_at' => $entity->getFetchedAt()?->format('Y-m-d H:i:s'),
                'analytics_processed_at' => $entity->getAnalyticsProcessedAt()?->format('Y-m-d H:i:s'),
                'fantasy_processed_at' => $entity->getFantasyProcessedAt()?->format('Y-m-d H:i:s'),
                'status' => $entity->getStatus()->value,
                'parsed_at' => $entity->getParsedAt()->format('Y-m-d H:i:s'),
            ],
            ['match_id' => $entity->getMatchId()->getValue()]
        );
    }

    /**
     * @throws Exception
     */
    public function findByMatchId(MatchId $matchId): ?MatchProcessing
    {
        $data = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE match_id = ?',
            [$matchId->getValue()]
        );

        if ($data === false) {
            return null;
        }

        return new MatchProcessing(
            matchId: $matchId,
            parsedAt: new DateTimeImmutable($data['parsed_at']),
            fetchedAt: $data['fetched_at'] ? new DateTimeImmutable($data['fetched_at']) : null,
            analyticsProcessedAt: $data['analytics_processed_at'] ? new DateTimeImmutable($data['analytics_processed_at']) : null,
            fantasyProcessedAt: $data['fantasy_processed_at'] ? new DateTimeImmutable($data['fantasy_processed_at']) : null,
            status: MatchProcessingStatus::from($data['status']),
            createdAt: $data['created_at'] ? new DateTimeImmutable($data['created_at']) : null,
        );
    }

    /**
     * @return MatchProcessing[]
     * @throws Exception
     */
    public function findByStatus(MatchProcessingStatus $status): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE status = ? ORDER BY parsed_at DESC',
            [$status->value]
        );

        $result = [];
        foreach ($rows as $data) {
            $result[] = new MatchProcessing(
                matchId: new MatchId($data['match_id']),
                parsedAt: new DateTimeImmutable($data['parsed_at']),
                fetchedAt: $data['fetched_at'] ? new DateTimeImmutable($data['fetched_at']) : null,
                analyticsProcessedAt: $data['analytics_processed_at'] ? new DateTimeImmutable($data['analytics_processed_at']) : null,
                fantasyProcessedAt: $data['fantasy_processed_at'] ? new DateTimeImmutable($data['fantasy_processed_at']) : null,
                status: MatchProcessingStatus::from($data['status']),
                createdAt: $data['created_at'] ? new DateTimeImmutable($data['created_at']) : null,
            );
        }

        return $result;
    }
}