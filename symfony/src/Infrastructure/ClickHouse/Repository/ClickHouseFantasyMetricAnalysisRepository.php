<?php
declare(strict_types=1);

namespace App\Infrastructure\ClickHouse\Repository;

use App\Domain\Fantasy\Entity\RoleGroupMetricRecommendation;
use App\Domain\Fantasy\Enum\RoleGroup;
use App\Domain\Fantasy\Repository\FantasyMetricAnalysisRepository;
use App\Domain\Fantasy\ValueObject\MetricContribution;
use ClickHouseDB\Client;
use DateTimeImmutable;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;

final readonly class ClickHouseFantasyMetricAnalysisRepository implements FantasyMetricAnalysisRepository
{
    private const array FANTASY_METRICS = [
        'kills_score',
        'deaths_score',
        'creeps_score',
        'gpm_score',
        'tower_score',
        'observer_score',
        'stacks_score',
        'rune_score',
        'roshan_score',
        'courier_score',
        'stun_score',
        'first_blood_score',
        'teamfight_score',
        'tormentor_score',
        'smoke_score',
        'watcher_score',
    ];

    public function __construct(
        private Client $clickhouse,
    ) {}

    public function getTopMetricsByRoleGroups(
        ?array $teamIds,
        ?array $playerIds,
        int $minMatchesPerPlayer,
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo,
        ?int $matchesLimit,
    ): array {
        $results = [];

        foreach (RoleGroup::cases() as $roleGroup) {
            $recommendation = $this->getTopMetricsForRoleGroup(
                $roleGroup,
                $teamIds,
                $playerIds,
                $minMatchesPerPlayer,
                $dateFrom,
                $dateTo,
                $matchesLimit
            );

            if ($recommendation !== null) {
                $results[] = $recommendation;
            }
        }

        return $results;
    }

    private function getTopMetricsForRoleGroup(
        RoleGroup $roleGroup,
        ?array $teamIds,
        ?array $playerIds,
        int $minMatchesPerPlayer,
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo,
        ?int $matchesLimit,
    ): ?RoleGroupMetricRecommendation {
        $roles = $roleGroup->getRoles();
        $rolesCondition = implode(', ', $roles);

        $whereConditions = ["pms.role IN ({$rolesCondition})"];
        $params = [];

        // Фильтрация по составу игроков (подход B - исключаем матчи с заменами)
        $excludedMatchesClause = '';
        if ($playerIds !== null && !empty($playerIds)) {
            /** @var PlayerId $playerId */
            $playerIdsValues = array_map(fn($playerId) => $playerId->getValue(), $playerIds);
            $playerIdsCondition = implode(', ', $playerIdsValues);
            
            // Находим матчи, где есть игроки НЕ из указанного списка (замены)
            $excludedMatchesClause = "
                AND pms.match_id NOT IN (
                    SELECT DISTINCT match_id 
                    FROM player_match_stats 
                    WHERE player_id NOT IN ({$playerIdsCondition})
                )
            ";
        }

        if ($teamIds !== null && !empty($teamIds)) {
            /** @var TeamId $teamId */
            $teamIdsValues = array_map(fn($teamId) => $teamId->getValue(), $teamIds);
            $teamIdsCondition = implode(', ', $teamIdsValues);
            $whereConditions[] = "pms.opendota_team_id IN ({$teamIdsCondition})";
        }

        if ($dateFrom !== null) {
            $whereConditions[] = 'pms.start_time >= :date_from';
            $params['date_from'] = $dateFrom->format('Y-m-d H:i:s');
        }

        if ($dateTo !== null) {
            $whereConditions[] = 'pms.start_time <= :date_to';
            $params['date_to'] = $dateTo->format('Y-m-d H:i:s');
        }

        $whereClause = implode(' AND ', $whereConditions) . $excludedMatchesClause;

        // Сначала получаем игроков с минимальным количеством матчей
        $playersQuery = "
            SELECT pms.player_id, count(*) as matches_count
            FROM player_match_stats pms
            WHERE {$whereClause}
            GROUP BY pms.player_id
            HAVING matches_count >= :min_matches
        ";

        $params['min_matches'] = $minMatchesPerPlayer;

        $playersStatement = $this->clickhouse->select($playersQuery, $params);
        $eligiblePlayers = $playersStatement->rows();

        if (empty($eligiblePlayers)) {
            return null;
        }

        // Получаем player_id список
        $playerIds = array_column($eligiblePlayers, 'player_id');
        $playerIdsStr = implode(', ', $playerIds);

        // Подготавливаем SELECT для всех метрик
        $metricSelects = [];
        foreach (self::FANTASY_METRICS as $metric) {
            $metricSelects[] = "avg(pmf.{$metric}) as avg_{$metric}";
        }
        $metricSelectsStr = implode(",\n                ", $metricSelects);

        // Основной запрос для получения средних значений метрик
        $limitClause = $matchesLimit !== null ? "LIMIT {$matchesLimit}" : '';

        $metricsQuery = "
            WITH limited_matches AS (
                SELECT 
                    pms.match_id,
                    pms.player_id,
                    pms.start_time
                FROM player_match_stats pms
                WHERE {$whereClause}
                  AND pms.player_id IN ({$playerIdsStr})
                ORDER BY pms.start_time DESC
                {$limitClause}
            )
            SELECT 
                {$metricSelectsStr},
                avg(pmf.total_score) as avg_total_score,
                count(DISTINCT pmf.player_id) as players_count,
                count(*) as matches_count
            FROM player_match_fantasy_stats pmf
            INNER JOIN limited_matches lm 
                ON pmf.match_id = lm.match_id AND pmf.player_id = lm.player_id
        ";

        $metricsStatement = $this->clickhouse->select($metricsQuery, $params);
        $metricsRow = $metricsStatement->rows();

        if (empty($metricsRow)) {
            return null;
        }

        $row = $metricsRow[0];
        $avgTotalScore = (float) $row['avg_total_score'];

        // Рассчитываем вклад каждой метрики
        $metricContributions = [];
        foreach (self::FANTASY_METRICS as $metric) {
            $avgValue = (float) $row["avg_{$metric}"];
            $contributionPercent = $avgTotalScore > 0 
                ? abs(($avgValue / $avgTotalScore) * 100) 
                : 0;

            $metricContributions[] = [
                'metric' => $metric,
                'avg_value' => $avgValue,
                'contribution' => $contributionPercent,
            ];
        }

        // Сортируем по вкладу
        usort($metricContributions, fn($a, $b) => $b['contribution'] <=> $a['contribution']);

        $topMetrics = array_map(
            fn($item) => new MetricContribution(
                metricName: $item['metric'],
                avgValue: $item['avg_value'],
                contributionPercent: round($item['contribution'], 2),
            ),
            $metricContributions
        );

        return new RoleGroupMetricRecommendation(
            roleGroup: $roleGroup,
            topMetrics: $topMetrics,
            matchesAnalyzed: (int) $row['matches_count'],
            playersAnalyzed: (int) $row['players_count'],
        );
    }

    public function getEligiblePlayerIds(
        ?array $teamIds,
        ?array $playerIds,
        int $minMatchesPerPlayer,
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo,
        ?int $matchesLimit,
    ): array {
        $whereConditions = [];
        $params = [];

        // Фильтрация по составу игроков (подход B - исключаем матчи с заменами)
        $excludedMatchesClause = '';
        if ($playerIds !== null && !empty($playerIds)) {
            /** @var PlayerId $playerId */
            $playerIdsValues = array_map(fn($playerId) => $playerId->getValue(), $playerIds);
            $playerIdsCondition = implode(', ', $playerIdsValues);
            
            $excludedMatchesClause = "
                AND pms.match_id NOT IN (
                    SELECT DISTINCT match_id 
                    FROM player_match_stats 
                    WHERE player_id NOT IN ({$playerIdsCondition})
                )
            ";
        }

        if ($teamIds !== null && !empty($teamIds)) {
            /** @var TeamId $teamId */
            $teamIdsValues = array_map(fn($teamId) => $teamId->getValue(), $teamIds);
            $teamIdsCondition = implode(', ', $teamIdsValues);
            $whereConditions[] = "pms.opendota_team_id IN ({$teamIdsCondition})";
        }

        if ($dateFrom !== null) {
            $whereConditions[] = 'pms.start_time >= :date_from';
            $params['date_from'] = $dateFrom->format('Y-m-d H:i:s');
        }

        if ($dateTo !== null) {
            $whereConditions[] = 'pms.start_time <= :date_to';
            $params['date_to'] = $dateTo->format('Y-m-d H:i:s');
        }

        $whereClause = !empty($whereConditions) 
            ? implode(' AND ', $whereConditions) . $excludedMatchesClause
            : '1=1' . $excludedMatchesClause;

        $playersQuery = "
            SELECT pms.player_id
            FROM player_match_stats pms
            WHERE {$whereClause}
            GROUP BY pms.player_id
            HAVING count(*) >= :min_matches
            ORDER BY pms.player_id
        ";

        $params['min_matches'] = $minMatchesPerPlayer;

        $statement = $this->clickhouse->select($playersQuery, $params);
        $rows = $statement->rows();

        return array_map(
            fn($row) => new PlayerId((int) $row['player_id']),
            $rows
        );
    }

    public function getEligibleTeamIds(
        ?array $teamIds,
        ?array $playerIds,
        int $minMatchesPerPlayer,
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo,
        ?int $matchesLimit,
    ): array {
        $whereConditions = [];
        $params = [];

        // Фильтрация по составу игроков (подход B - исключаем матчи с заменами)
        $excludedMatchesClause = '';
        if ($playerIds !== null && !empty($playerIds)) {
            /** @var PlayerId $playerId */
            $playerIdsValues = array_map(fn($playerId) => $playerId->getValue(), $playerIds);
            $playerIdsCondition = implode(', ', $playerIdsValues);
            
            $excludedMatchesClause = "
                AND pms.match_id NOT IN (
                    SELECT DISTINCT match_id 
                    FROM player_match_stats 
                    WHERE player_id NOT IN ({$playerIdsCondition})
                )
            ";
        }

        if ($teamIds !== null && !empty($teamIds)) {
            /** @var TeamId $teamId */
            $teamIdsValues = array_map(fn($teamId) => $teamId->getValue(), $teamIds);
            $teamIdsCondition = implode(', ', $teamIdsValues);
            $whereConditions[] = "pms.opendota_team_id IN ({$teamIdsCondition})";
        }

        if ($dateFrom !== null) {
            $whereConditions[] = 'pms.start_time >= :date_from';
            $params['date_from'] = $dateFrom->format('Y-m-d H:i:s');
        }

        if ($dateTo !== null) {
            $whereConditions[] = 'pms.start_time <= :date_to';
            $params['date_to'] = $dateTo->format('Y-m-d H:i:s');
        }

        $whereClause = !empty($whereConditions) 
            ? implode(' AND ', $whereConditions) . $excludedMatchesClause
            : '1=1' . $excludedMatchesClause;

        // Сначала получаем eligible player_ids
        $eligiblePlayersSubquery = "
            SELECT pms.player_id
            FROM player_match_stats pms
            WHERE {$whereClause}
            GROUP BY pms.player_id
            HAVING count(*) >= :min_matches
        ";

        // Затем получаем уникальные team_ids для этих игроков
        // Если был передан фильтр team_ids, учитываем только эти команды
        $teamFilterClause = '';
        if ($teamIds !== null && !empty($teamIds)) {
            /** @var TeamId $teamId */
            $teamIdsValues = array_map(fn($teamId) => $teamId->getValue(), $teamIds);
            $teamIdsCondition = implode(', ', $teamIdsValues);
            $teamFilterClause = "AND pms.opendota_team_id IN ({$teamIdsCondition})";
        }

        $teamsQuery = "
            SELECT DISTINCT pms.opendota_team_id
            FROM player_match_stats pms
            WHERE pms.player_id IN ({$eligiblePlayersSubquery})
              AND pms.opendota_team_id > 0
              {$teamFilterClause}
            ORDER BY pms.opendota_team_id
        ";

        $params['min_matches'] = $minMatchesPerPlayer;

        $statement = $this->clickhouse->select($teamsQuery, $params);
        $rows = $statement->rows();

        return array_map(
            fn($row) => new TeamId((int) $row['opendota_team_id']),
            $rows
        );
    }
}
