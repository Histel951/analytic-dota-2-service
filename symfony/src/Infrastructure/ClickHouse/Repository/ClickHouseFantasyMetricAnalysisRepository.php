<?php
declare(strict_types=1);

namespace App\Infrastructure\ClickHouse\Repository;

use App\Domain\Fantasy\Entity\PlayerInCombo;
use App\Domain\Fantasy\Entity\RoleGroupMetricRecommendation;
use App\Domain\Fantasy\Entity\TopComboRecommendation;
use App\Domain\Fantasy\Enum\RoleGroup;
use App\Domain\Fantasy\Repository\FantasyMetricAnalysisRepository;
use App\Domain\Fantasy\ValueObject\MetricContribution;
use App\Domain\Fantasy\ValueObject\PlayerMetrics;
use App\Domain\Match\Repository\MatchPlayersRepository;
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
        private MatchPlayersRepository $matchPlayersRepository,
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

        // Получаем player_id список для eligible игроков
        $eligiblePlayerIds = array_column($eligiblePlayers, 'player_id');
        $eligiblePlayerIdsStr = implode(', ', $eligiblePlayerIds);

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
                  AND pms.player_id IN ({$eligiblePlayerIdsStr})
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
        $topThree = array_slice($metricContributions, 0, 3);

        $topMetrics = array_map(
            fn($item) => new MetricContribution(
                metricName: $item['metric'],
                avgValue: $item['avg_value'],
                contributionPercent: round($item['contribution'], 2),
            ),
            $topThree
        );

        // Получаем топ-3 комбо для этой role group
        $topCombos = $this->getTopCombosForRoleGroup(
            $roleGroup,
            $teamIds,
            $playerIds,
            $minMatchesPerPlayer,
            $dateFrom,
            $dateTo,
            $matchesLimit,
            $topThree,
        );

        return new RoleGroupMetricRecommendation(
            roleGroup: $roleGroup,
            topMetrics: $topMetrics,
            topCombos: $topCombos,
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

    /**
     * Получает топ-3 комбо игроков для role group
     * 
     * @param array $topThreeMetrics Массив топ-3 метрик ['metric' => ..., 'avg_value' => ..., 'contribution' => ...]
     * @return TopComboRecommendation[]
     */
    private function getTopCombosForRoleGroup(
        RoleGroup $roleGroup,
        ?array $teamIds,
        ?array $playerIds,
        int $minMatchesPerPlayer,
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo,
        ?int $matchesLimit,
        array $topThreeMetrics,
    ): array {
        $roles = $roleGroup->getRoles();
        
        // Для mid (1 роль) - просто топ-3 игрока
        if (count($roles) === 1) {
            return $this->getTopPlayersForSingleRole(
                $roles[0],
                $teamIds,
                $playerIds,
                $minMatchesPerPlayer,
                $dateFrom,
                $dateTo,
                $matchesLimit,
                $topThreeMetrics,
            );
        }
        
        // Для core_side и support (2 роли) - топ-3 пары из одной команды
        return $this->getTopCombosForDoubleRoles(
            $roles,
            $teamIds,
            $playerIds,
            $minMatchesPerPlayer,
            $dateFrom,
            $dateTo,
            $matchesLimit,
            $topThreeMetrics,
        );
    }

    /**
     * Получает топ-3 игрока для роли с одним слотом (mid)
     * 
     * @return TopComboRecommendation[]
     */
    private function getTopPlayersForSingleRole(
        int $role,
        ?array $teamIds,
        ?array $playerIds,
        int $minMatchesPerPlayer,
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo,
        ?int $matchesLimit,
        array $topThreeMetrics,
    ): array {
        $whereConditions = ["pms.role = {$role}"];
        $params = [];

        // Фильтрация по составу игроков
        $excludedMatchesClause = '';
        if ($playerIds !== null && !empty($playerIds)) {
            // Проверяем тип первого элемента и преобразуем соответственно
            $firstElement = reset($playerIds);
            $playerIdsValues = $firstElement instanceof PlayerId
                ? array_map(fn($playerId) => $playerId->getValue(), $playerIds)
                : $playerIds;
            
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

        // Подготавливаем SELECT для всех метрик
        $metricSelects = [];
        foreach (self::FANTASY_METRICS as $metric) {
            $metricSelects[] = "avg(pmf.{$metric}) as avg_{$metric}";
        }
        $metricSelectsStr = implode(",\n                ", $metricSelects);

        $limitClause = $matchesLimit !== null ? "LIMIT {$matchesLimit}" : '';

        $query = "
            WITH eligible_players AS (
                SELECT 
                    pms.player_id,
                    pms.opendota_team_id
                FROM player_match_stats pms
                WHERE {$whereClause}
                GROUP BY pms.player_id, pms.opendota_team_id
                HAVING count(*) >= :min_matches
            ),
            limited_matches AS (
                SELECT 
                    pms.match_id,
                    pms.player_id,
                    pms.start_time
                FROM player_match_stats pms
                WHERE {$whereClause}
                  AND pms.player_id IN (SELECT player_id FROM eligible_players)
                ORDER BY pms.start_time DESC
                {$limitClause}
            )
            SELECT 
                pmf.player_id as player_id,
                ep.opendota_team_id as opendota_team_id,
                {$role} as role,
                {$metricSelectsStr},
                avg(pmf.total_score) as avg_total_score,
                count(*) as matches_count
            FROM player_match_fantasy_stats pmf
            INNER JOIN limited_matches lm 
                ON pmf.match_id = lm.match_id AND pmf.player_id = lm.player_id
            INNER JOIN eligible_players ep
                ON pmf.player_id = ep.player_id
            GROUP BY player_id, opendota_team_id
            ORDER BY avg_total_score DESC
            LIMIT 16
        ";

        $params['min_matches'] = $minMatchesPerPlayer;

        $statement = $this->clickhouse->select($query, $params);
        $rows = $statement->rows();

        // Debug: логируем количество строк
        error_log(sprintf(
            "Single role %d: returned %d rows, min_matches=%d, teamIds=%s, playerIds=%s",
            $role,
            count($rows),
            $minMatchesPerPlayer,
            $teamIds !== null ? count($teamIds) . ' teams' : 'null',
            $playerIds !== null ? count($playerIds) . ' players' : 'null'
        ));
        
        if (empty($rows)) {
            error_log("Empty result for single role {$role}");
            return [];
        }

        return $this->mapRowsToTopCombos($rows, $topThreeMetrics, true);
    }

    /**
     * Получает топ-3 пары игроков для ролей с двумя слотами (core_side, support)
     * 
     * @return TopComboRecommendation[]
     */
    private function getTopCombosForDoubleRoles(
        array $roles,
        ?array $teamIds,
        ?array $playerIds,
        int $minMatchesPerPlayer,
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo,
        ?int $matchesLimit,
        array $topThreeMetrics,
    ): array {
        $rolesCondition = implode(', ', $roles);
        $whereConditions = ["pms.role IN ({$rolesCondition})"];
        $params = [];

        // Фильтрация по составу игроков
        $excludedMatchesClause = '';
        if ($playerIds !== null && !empty($playerIds)) {
            // Проверяем тип первого элемента и преобразуем соответственно
            $firstElement = reset($playerIds);
            $playerIdsValues = $firstElement instanceof PlayerId
                ? array_map(fn($playerId) => $playerId->getValue(), $playerIds)
                : $playerIds;
            
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

        // Подготавливаем SELECT для всех метрик
        $metricSelects = [];
        foreach (self::FANTASY_METRICS as $metric) {
            $metricSelects[] = "avg(pmf.{$metric}) as avg_{$metric}";
        }
        $metricSelectsStr = implode(",\n                ", $metricSelects);

        $limitClause = $matchesLimit !== null ? "LIMIT {$matchesLimit}" : '';

        $query = "
            WITH eligible_players AS (
                SELECT 
                    pms.player_id,
                    pms.opendota_team_id,
                    pms.role
                FROM player_match_stats pms
                WHERE {$whereClause}
                GROUP BY pms.player_id, pms.opendota_team_id, pms.role
                HAVING count(*) >= :min_matches
            ),
            limited_matches AS (
                SELECT 
                    pms.match_id,
                    pms.player_id,
                    pms.start_time
                FROM player_match_stats pms
                WHERE {$whereClause}
                  AND pms.player_id IN (SELECT player_id FROM eligible_players)
                ORDER BY pms.start_time DESC
                {$limitClause}
            ),
            player_metrics AS (
                SELECT 
                    pmf.player_id as player_id,
                    ep.opendota_team_id as opendota_team_id,
                    ep.role as role,
                    {$metricSelectsStr},
                    avg(pmf.total_score) as avg_total_score,
                    count(*) as matches_count
                FROM player_match_fantasy_stats pmf
                INNER JOIN limited_matches lm 
                    ON pmf.match_id = lm.match_id AND pmf.player_id = lm.player_id
                INNER JOIN eligible_players ep
                    ON pmf.player_id = ep.player_id
                GROUP BY player_id, opendota_team_id, role
            ),
            team_combos AS (
                SELECT 
                    opendota_team_id,
                    sum(avg_total_score) as combined_score,
                    min(matches_count) as min_matches_count,
                    count(*) as players_in_combo
                FROM player_metrics
                GROUP BY opendota_team_id
                HAVING players_in_combo >= 1
            ),
            top_teams AS (
                SELECT opendota_team_id
                FROM team_combos
                ORDER BY combined_score DESC
                LIMIT 16
            )
            SELECT 
                pm.opendota_team_id,
                pm.player_id,
                pm.role,
                pm.avg_total_score,
                pm.matches_count,
                " . implode(",\n                ", array_map(fn($m) => "pm.avg_{$m}", self::FANTASY_METRICS)) . ",
                tc.combined_score,
                tc.min_matches_count
            FROM player_metrics pm
            INNER JOIN team_combos tc ON pm.opendota_team_id = tc.opendota_team_id
            WHERE pm.opendota_team_id IN (SELECT opendota_team_id FROM top_teams)
            ORDER BY tc.combined_score DESC, pm.opendota_team_id, pm.role
        ";

        $params['min_matches'] = $minMatchesPerPlayer;

        $statement = $this->clickhouse->select($query, $params);
        $rows = $statement->rows();

        // Debug: логируем количество строк
        error_log(sprintf(
            "Double roles [%s]: returned %d rows, min_matches=%d, teamIds=%s, playerIds=%s",
            implode(',', $roles),
            count($rows),
            $minMatchesPerPlayer,
            $teamIds !== null ? count($teamIds) . ' teams' : 'null',
            $playerIds !== null ? count($playerIds) . ' players' : 'null'
        ));
        
        if (empty($rows)) {
            error_log("Empty result for double roles [" . implode(',', $roles) . "]");
            return [];
        }

        return $this->mapRowsToTopCombosGrouped($rows, $topThreeMetrics);
    }

    /**
     * Маппинг строк в TopComboRecommendation для одиночных ролей
     * 
     * @return TopComboRecommendation[]
     */
    private function mapRowsToTopCombos(array $rows, array $topThreeMetrics, bool $isSingleRole): array
    {
        if (empty($rows)) {
            return [];
        }

        // Получаем все player_ids для запроса nicknames
        $playerIds = array_map(fn($row) => (int) $row['player_id'], $rows);
        $nicknames = $this->matchPlayersRepository->getNicknamesByIds($playerIds);

        $topThreeMetricNames = array_column($topThreeMetrics, 'metric');

        $combos = [];
        foreach ($rows as $row) {
            $playerId = (int) $row['player_id'];
            $nickname = $nicknames[$playerId] ?? 'Unknown';

            // Создаем массив всех метрик
            $allMetrics = [];
            foreach (self::FANTASY_METRICS as $metric) {
                $avgValue = (float) $row["avg_{$metric}"];
                
                $allMetrics[] = [
                    'metric' => $metric,
                    'avgValue' => $avgValue,
                ];
            }

            // Сортируем метрики по значению (от большего к меньшему)
            usort($allMetrics, fn($a, $b) => $b['avgValue'] <=> $a['avgValue']);

            // Создаем объекты PlayerMetrics с правильным флагом isTopMetric
            $playerMetrics = [];
            foreach ($allMetrics as $index => $metricData) {
                $isTopMetric = $index < 3; // Первые 3 метрики - топовые
                
                $playerMetrics[] = new PlayerMetrics(
                    metricName: $metricData['metric'],
                    avgValue: $metricData['avgValue'],
                    isTopMetric: $isTopMetric,
                );
            }

            $player = new PlayerInCombo(
                playerId: new PlayerId($playerId),
                nickname: $nickname,
                role: $row['role'] ?? 2, // для mid по умолчанию
                avgTotalScore: (float) $row['avg_total_score'],
                metrics: $playerMetrics,
                matchesCount: (int) $row['matches_count'],
            );

            $combos[] = new TopComboRecommendation(
                teamId: new TeamId((int) $row['opendota_team_id']),
                players: [$player],
                avgCombinedScore: (float) $row['avg_total_score'],
                matchesCount: (int) $row['matches_count'],
            );
        }

        return $combos;
    }

    /**
     * Маппинг сгруппированных строк в TopComboRecommendation для парных ролей
     * 
     * @return TopComboRecommendation[]
     */
    private function mapRowsToTopCombosGrouped(array $rows, array $topThreeMetrics): array
    {
        if (empty($rows)) {
            return [];
        }

        // Получаем все player_ids для запроса nicknames
        $playerIds = array_unique(array_map(fn($row) => (int) $row['player_id'], $rows));
        $nicknames = $this->matchPlayersRepository->getNicknamesByIds($playerIds);

        $topThreeMetricNames = array_column($topThreeMetrics, 'metric');

        // Группируем по командам
        $teamGroups = [];
        foreach ($rows as $row) {
            $teamId = (int) $row['opendota_team_id'];
            if (!isset($teamGroups[$teamId])) {
                $teamGroups[$teamId] = [
                    'combined_score' => (float) $row['combined_score'],
                    'min_matches_count' => (int) $row['min_matches_count'],
                    'players' => [],
                ];
            }

            $playerId = (int) $row['player_id'];
            $nickname = $nicknames[$playerId] ?? 'Unknown';

            // Создаем массив всех метрик
            $allMetrics = [];
            foreach (self::FANTASY_METRICS as $metric) {
                $avgValue = (float) $row["avg_{$metric}"];
                $isTopMetric = in_array($metric, $topThreeMetricNames, true);
                
                $allMetrics[] = new PlayerMetrics(
                    metricName: $metric,
                    avgValue: $avgValue,
                    isTopMetric: $isTopMetric,
                );
            }

            // Сортируем метрики по значению (от большего к меньшему)
            usort($allMetrics, fn($a, $b) => $b->getAvgValue() <=> $a->getAvgValue());

            $teamGroups[$teamId]['players'][] = new PlayerInCombo(
                playerId: new PlayerId($playerId),
                nickname: $nickname,
                role: (int) $row['role'],
                avgTotalScore: (float) $row['avg_total_score'],
                metrics: $allMetrics,
                matchesCount: (int) $row['matches_count'],
            );
        }

        // Создаем TopComboRecommendation для каждой команды
        $combos = [];
        foreach ($teamGroups as $teamId => $group) {
            $combos[] = new TopComboRecommendation(
                teamId: new TeamId($teamId),
                players: $group['players'],
                avgCombinedScore: $group['combined_score'],
                matchesCount: $group['min_matches_count'],
            );
        }

        // Берем только топ-3
        return array_slice($combos, 0, 16);
    }
}
