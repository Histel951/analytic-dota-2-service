<?php
declare(strict_types=1);

namespace App\Infrastructure\ClickHouse\Repository;

use App\Domain\Team\Entity\TeamFormAnalysis;
use App\Domain\Team\Enum\TeamForm;
use App\Domain\Team\Repository\TeamFormAnalysisRepository;
use App\Domain\Team\ValueObjects\Winrate;
use App\Infrastructure\ClickHouse\ClickHouseFactory;
use DateTimeImmutable;
use App\Shared\Domain\MatchId;
use App\Shared\Domain\Region\Region;
use App\Shared\Domain\TeamId;

final readonly class ClickHouseTeamFormAnalysisRepository implements TeamFormAnalysisRepository
{
    public function __construct(
        private ClickHouseFactory $clickHouseFactory,
    ) {}

    public function save(TeamFormAnalysis $analysis): void
    {
        $db = $this->clickHouseFactory->create();

        $matchIds = array_map(
            fn(MatchId $matchId) => $matchId->getValue(),
            $analysis->getMatchesAnalyzed()
        );

        $db->insert('team_form_analysis', [[
            'team_id' => $analysis->getTeamId()->getValue(),
            'team_name' => $analysis->getTeamName(),
            'form' => $analysis->getForm()->value,
            'winrate' => $analysis->getWinrate()->getValue(),
            'region' => $analysis->getRegion()->getName(),
            'matches_analyzed' => $matchIds,
            'last_match_time' => $analysis->getLastMatchTime()->format('Y-m-d H:i:s'),
            'analyzed_at' => $analysis->getAnalyzedAt()->format('Y-m-d H:i:s'),
            'matches_count' => $analysis->getMatchesCount(),
        ]]);
    }

    public function findByTeamId(TeamId $teamId): ?TeamFormAnalysis
    {
        $db = $this->clickHouseFactory->create();

        $result = $db->select(
            'SELECT * FROM team_form_analysis WHERE team_id = :team_id ORDER BY analyzed_at DESC LIMIT 1',
            ['team_id' => $teamId->getValue()]
        );

        if (empty($result->rows())) {
            return null;
        }

        return $this->mapToEntity($result->rows()[0]);
    }

    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $db = $this->clickHouseFactory->create();

        $result = $db->select(
            'SELECT * FROM team_form_analysis ORDER BY analyzed_at DESC LIMIT :limit OFFSET :offset',
            ['limit' => $limit, 'offset' => $offset]
        );

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $result->rows()
        );
    }

    public function findFavorites(int $limit = 10): array
    {
        $db = $this->clickHouseFactory->create();

        $result = $db->select(
            'SELECT * FROM team_form_analysis WHERE form IN (\'prime\', \'high\') ORDER BY winrate DESC, form ASC LIMIT :limit',
            ['limit' => $limit]
        );

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $result->rows()
        );
    }

    public function findUnderdogs(int $limit = 10): array
    {
        $db = $this->clickHouseFactory->create();

        $result = $db->select(
            'SELECT * FROM team_form_analysis WHERE form IN (\'low\', \'medium\') ORDER BY winrate ASC, form DESC LIMIT :limit',
            ['limit' => $limit]
        );

        return array_map(
            fn(array $row) => $this->mapToEntity($row),
            $result->rows()
        );
    }

    private function mapToEntity(array $row): TeamFormAnalysis
    {
        $matchIds = array_map(
            fn(int $matchId) => new MatchId($matchId),
            $row['matches_analyzed']
        );

        return new TeamFormAnalysis(
            teamId: new TeamId($row['team_id']),
            teamName: $row['team_name'],
            form: TeamForm::from($row['form']),
            winrate: new Winrate($row['winrate']),
            region: new Region(0, $row['region'], strtolower($row['region'])),
            matchesAnalyzed: $matchIds,
            lastMatchTime: new DateTimeImmutable($row['last_match_time']),
            analyzedAt: new DateTimeImmutable($row['analyzed_at']),
        );
    }
}
