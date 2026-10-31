<?php
declare(strict_types=1);

namespace App\Infrastructure\ClickHouse\Repository;

use App\Domain\Fantasy\Entity\PlayerInCombo;
use App\Domain\Fantasy\Entity\RoleGroupMetricRecommendation;
use App\Domain\Fantasy\Entity\TopComboRecommendation;
use App\Domain\Fantasy\Enum\RoleGroup;
use App\Domain\Fantasy\Repository\FantasyMetricAnalysisRepository;
use App\Domain\Fantasy\Service\SelectTopMetricsByEmblemRules;
use App\Domain\Fantasy\ValueObject\MetricContribution;
use App\Domain\Fantasy\ValueObject\PlayerAnalyzedMatch;
use App\Domain\Fantasy\ValueObject\PlayerMatchStatistic;
use App\Domain\Fantasy\ValueObject\PlayerMetrics;
use App\Domain\Match\Repository\MatchPlayersRepository;
use ClickHouseDB\Client;
use DateTimeImmutable;
use App\Shared\Domain\PlayerId;
use App\Shared\Domain\TeamId;

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
        private SelectTopMetricsByEmblemRules $selectTopMetricsByEmblemRules,
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
        $limitedMatchesCte = $this->buildLimitedMatchesCte(
            $whereClause,
            $eligiblePlayerIdsStr,
            $matchesLimit,
        );

        $metricsQuery = "
            WITH {$limitedMatchesCte}
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

        // Топ-метрики по квотам эмблем role group (core/utility/support)
        $topMetrics = $this->selectTopMetricsByEmblemRules->select($roleGroup, $metricContributions);

        // Получаем комбо игроков для этой role group (все, по убыванию score)
        $topCombos = $this->getTopCombosForRoleGroup(
            $roleGroup,
            $teamIds,
            $playerIds,
            $minMatchesPerPlayer,
            $dateFrom,
            $dateTo,
            $matchesLimit,
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
     * Получает комбо игроков для role group (все, по убыванию score)
     *
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
    ): array {
        $roles = $roleGroup->getRoles();
        
        // Для mid (1 роль) - все игроки по убыванию score
        if (count($roles) === 1) {
            return $this->getTopPlayersForSingleRole(
                $roleGroup,
                $roles[0],
                $teamIds,
                $playerIds,
                $minMatchesPerPlayer,
                $dateFrom,
                $dateTo,
                $matchesLimit,
            );
        }
        
        // Для core_side и support (2 роли) - все пары из одной команды
        return $this->getTopCombosForDoubleRoles(
            $roleGroup,
            $roles,
            $teamIds,
            $playerIds,
            $minMatchesPerPlayer,
            $dateFrom,
            $dateTo,
            $matchesLimit,
        );
    }

    /**
     * Получает игроков для роли с одним слотом (mid), по убыванию score
     * 
     * @return TopComboRecommendation[]
     */
    private function getTopPlayersForSingleRole(
        RoleGroup $roleGroup,
        int $role,
        ?array $teamIds,
        ?array $playerIds,
        int $minMatchesPerPlayer,
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo,
        ?int $matchesLimit,
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

        $limitedMatchesCte = $this->buildLimitedMatchesCte(
            $whereClause,
            'SELECT player_id FROM eligible_players',
            $matchesLimit,
        );

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
            {$limitedMatchesCte}
            SELECT 
                pmf.player_id AS player_id,
                ep.opendota_team_id AS opendota_team_id,
                {$role} AS role,
                {$metricSelectsStr},
                avg(pmf.total_score) AS avg_total_score,
                count(*) AS matches_count,
                avg(lm.duration) AS avg_match_duration
            FROM player_match_fantasy_stats pmf
            INNER JOIN limited_matches lm 
                ON pmf.match_id = lm.match_id AND pmf.player_id = lm.player_id
            INNER JOIN eligible_players ep
                ON pmf.player_id = ep.player_id
               AND ep.opendota_team_id = lm.opendota_team_id
            GROUP BY player_id, opendota_team_id
            ORDER BY avg_total_score DESC
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

        $matchesByPlayerTeam = $this->fetchPlayerMatchesByRows(
            $rows,
            $whereClause,
            "
                eligible_players AS (
                    SELECT 
                        pms.player_id,
                        pms.opendota_team_id
                    FROM player_match_stats pms
                    WHERE {$whereClause}
                    GROUP BY pms.player_id, pms.opendota_team_id
                    HAVING count(*) >= :min_matches
                )
            ",
            'SELECT player_id FROM eligible_players',
            $matchesLimit,
            $params,
        );

        return $this->mapRowsToTopCombos($rows, $roleGroup, $matchesByPlayerTeam);
    }

    /**
     * Получает пары игроков для ролей с двумя слотами (core_side, support), по убыванию score
     * 
     * @return TopComboRecommendation[]
     */
    private function getTopCombosForDoubleRoles(
        RoleGroup $roleGroup,
        array $roles,
        ?array $teamIds,
        ?array $playerIds,
        int $minMatchesPerPlayer,
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo,
        ?int $matchesLimit,
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

        $limitedMatchesCte = $this->buildLimitedMatchesCte(
            $whereClause,
            'SELECT player_id FROM eligible_players',
            $matchesLimit,
        );

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
            {$limitedMatchesCte},
            player_metrics AS (
                SELECT 
                    pmf.player_id as player_id,
                    ep.opendota_team_id as opendota_team_id,
                    ep.role as role,
                    {$metricSelectsStr},
                    avg(pmf.total_score) as avg_total_score,
                    count(*) as matches_count,
                    avg(lm.duration) as avg_match_duration
                FROM player_match_fantasy_stats pmf
                INNER JOIN limited_matches lm 
                    ON pmf.match_id = lm.match_id AND pmf.player_id = lm.player_id
                INNER JOIN eligible_players ep
                    ON pmf.player_id = ep.player_id
                   AND ep.opendota_team_id = lm.opendota_team_id
                GROUP BY player_id, opendota_team_id, role
            ),
            team_combos AS (
                SELECT 
                    opendota_team_id as opendota_team_id,
                    sum(avg_total_score) as combined_score,
                    min(matches_count) as min_matches_count,
                    count(*) as players_in_combo,
                    avg(avg_match_duration) as avg_match_duration
                FROM player_metrics
                GROUP BY opendota_team_id
                HAVING players_in_combo >= 1
            )
            SELECT 
                pm.opendota_team_id AS opendota_team_id,
                pm.player_id AS player_id,
                pm.role AS role,
                pm.avg_total_score AS avg_total_score,
                pm.matches_count AS matches_count,
                " . implode(",\n                ", array_map(fn($m) => "pm.avg_{$m} AS avg_{$m}", self::FANTASY_METRICS)) . ",
                tc.combined_score AS combined_score,
                tc.min_matches_count AS min_matches_count,
                tc.avg_match_duration AS avg_match_duration
            FROM player_metrics pm
            INNER JOIN team_combos tc ON pm.opendota_team_id = tc.opendota_team_id
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

        $matchesByPlayerTeam = $this->fetchPlayerMatchesByRows(
            $rows,
            $whereClause,
            "
                eligible_players AS (
                    SELECT 
                        pms.player_id,
                        pms.opendota_team_id,
                        pms.role
                    FROM player_match_stats pms
                    WHERE {$whereClause}
                    GROUP BY pms.player_id, pms.opendota_team_id, pms.role
                    HAVING count(*) >= :min_matches
                )
            ",
            'SELECT player_id FROM eligible_players',
            $matchesLimit,
            $params,
        );

        return $this->mapRowsToTopCombosGrouped($rows, $roleGroup, $matchesByPlayerTeam);
    }

    /**
     * Маппинг строк в TopComboRecommendation для одиночных ролей
     *
     * @param array<string, PlayerAnalyzedMatch[]> $matchesByPlayerTeam
     * @return TopComboRecommendation[]
     */
    private function mapRowsToTopCombos(array $rows, RoleGroup $roleGroup, array $matchesByPlayerTeam): array
    {
        if (empty($rows)) {
            return [];
        }

        $playerIds = array_map(fn($row) => (int) $row['player_id'], $rows);
        $nicknames = $this->matchPlayersRepository->getNicknamesByIds($playerIds);

        $combos = [];
        foreach ($rows as $row) {
            $playerId = (int) $row['player_id'];
            $teamId = (int) $row['opendota_team_id'];
            $role = (int) ($row['role'] ?? 2);
            $key = $this->playerTeamKey($playerId, $teamId);

            $player = new PlayerInCombo(
                playerId: new PlayerId($playerId),
                nickname: $nicknames[$playerId] ?? 'Unknown',
                role: $role,
                avgTotalScore: (float) $row['avg_total_score'],
                metrics: $this->buildPlayerMetrics($row, $role),
                matchesCount: (int) $row['matches_count'],
                matches: $matchesByPlayerTeam[$key] ?? [],
            );

            $players = [$player];

            $combos[] = new TopComboRecommendation(
                teamId: new TeamId($teamId),
                topMetrics: $this->buildComboTopMetrics($players, $roleGroup),
                players: $players,
                avgCombinedScore: (float) $row['avg_total_score'],
                matchesCount: (int) $row['matches_count'],
                avgMatchDuration: (float) $row['avg_match_duration'],
            );
        }

        return $combos;
    }

    /**
     * Маппинг сгруппированных строк в TopComboRecommendation для парных ролей
     *
     * @param array<string, PlayerAnalyzedMatch[]> $matchesByPlayerTeam
     * @return TopComboRecommendation[]
     */
    private function mapRowsToTopCombosGrouped(array $rows, RoleGroup $roleGroup, array $matchesByPlayerTeam): array
    {
        if (empty($rows)) {
            return [];
        }

        $playerIds = array_unique(array_map(fn($row) => (int) $row['player_id'], $rows));
        $nicknames = $this->matchPlayersRepository->getNicknamesByIds($playerIds);

        $teamGroups = [];
        foreach ($rows as $row) {
            $teamId = (int) $row['opendota_team_id'];
            if (!isset($teamGroups[$teamId])) {
                $teamGroups[$teamId] = [
                    'combined_score' => (float) $row['combined_score'],
                    'min_matches_count' => (int) $row['min_matches_count'],
                    'avg_match_duration' => (float) $row['avg_match_duration'],
                    'players' => [],
                ];
            }

            $playerId = (int) $row['player_id'];
            $role = (int) $row['role'];
            $key = $this->playerTeamKey($playerId, $teamId);

            $teamGroups[$teamId]['players'][] = new PlayerInCombo(
                playerId: new PlayerId($playerId),
                nickname: $nicknames[$playerId] ?? 'Unknown',
                role: $role,
                avgTotalScore: (float) $row['avg_total_score'],
                metrics: $this->buildPlayerMetrics($row, $role),
                matchesCount: (int) $row['matches_count'],
                matches: $matchesByPlayerTeam[$key] ?? [],
            );
        }

        $combos = [];
        foreach ($teamGroups as $teamId => $group) {
            $combos[] = new TopComboRecommendation(
                teamId: new TeamId($teamId),
                topMetrics: $this->buildComboTopMetrics($group['players'], $roleGroup),
                players: $group['players'],
                avgCombinedScore: $group['combined_score'],
                matchesCount: $group['min_matches_count'],
                avgMatchDuration: $group['avg_match_duration'],
            );
        }

        return $combos;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, PlayerAnalyzedMatch[]>
     */
    private function fetchPlayerMatchesByRows(
        array $rows,
        string $whereClause,
        string $eligiblePlayersCte,
        string $eligiblePlayersFilter,
        ?int $matchesLimit,
        array $params,
    ): array {
        if (empty($rows)) {
            return [];
        }

        $playerIds = array_values(array_unique(array_map(
            fn(array $row) => (int) $row['player_id'],
            $rows
        )));
        $playerIdsCondition = implode(', ', $playerIds);

        $pairConditions = [];
        foreach ($rows as $row) {
            $pairConditions[$this->playerTeamKey((int) $row['player_id'], (int) $row['opendota_team_id'])] = sprintf(
                '(player_id = %d AND opendota_team_id = %d)',
                (int) $row['player_id'],
                (int) $row['opendota_team_id']
            );
        }
        $pairsCondition = implode(' OR ', array_values($pairConditions));

        $limitedMatchesCte = $this->buildLimitedMatchesCte(
            $whereClause,
            $eligiblePlayersFilter,
            $matchesLimit,
        );

        $query = "
            WITH {$eligiblePlayersCte},
            {$limitedMatchesCte}
            SELECT
                player_id AS player_id,
                opendota_team_id AS opendota_team_id,
                match_id AS match_id,
                duration AS match_duration,
                start_time AS start_time,
                kills AS kills,
                deaths AS deaths,
                assists AS assists,
                gpm AS gpm,
                xpm AS xpm,
                net_worth AS net_worth,
                creeps_last_hits AS creeps_last_hits,
                creeps_denies AS creeps_denies,
                opendota_hero_id AS opendota_hero_id,
                win AS is_win
            FROM limited_matches
            WHERE player_id IN ({$playerIdsCondition})
              AND ({$pairsCondition})
            ORDER BY player_id, opendota_team_id, start_time DESC
        ";

        $statement = $this->clickhouse->select($query, $params);
        $matchRows = $statement->rows();

        $result = [];
        foreach ($matchRows as $matchRow) {
            $key = $this->playerTeamKey(
                (int) $matchRow['player_id'],
                (int) $matchRow['opendota_team_id']
            );

            $result[$key][] = new PlayerAnalyzedMatch(
                matchId: (int) $matchRow['match_id'],
                matchDuration: (int) $matchRow['match_duration'],
                startTime: new DateTimeImmutable((string) $matchRow['start_time']),
                statistic: new PlayerMatchStatistic(
                    kills: (int) $matchRow['kills'],
                    deaths: (int) $matchRow['deaths'],
                    assists: (int) $matchRow['assists'],
                    gpm: (int) $matchRow['gpm'],
                    xpm: (int) $matchRow['xpm'],
                    netWorth: (int) $matchRow['net_worth'],
                    creepsLastHits: (int) $matchRow['creeps_last_hits'],
                    creepsDenies: (int) $matchRow['creeps_denies'],
                    opendotaHeroId: (int) $matchRow['opendota_hero_id'],
                ),
                isWin: (bool) $matchRow['is_win'],
            );
        }

        return $result;
    }

    private function playerTeamKey(int $playerId, int $teamId): string
    {
        return $playerId . '_' . $teamId;
    }

    /**
     * CTE limited_matches: последние N матчей на каждую команду (не глобальный LIMIT).
     *
     * @param string $eligiblePlayersFilter SQL для IN (...): список id или подзапрос
     */
    private function buildLimitedMatchesCte(
        string $whereClause,
        string $eligiblePlayersFilter,
        ?int $matchesLimit,
    ): string {
        $matchStatsSelect = '
            pms.match_id,
            pms.player_id,
            pms.start_time,
            pms.duration,
            pms.opendota_team_id,
            pms.kills,
            pms.deaths,
            pms.assists,
            pms.gpm,
            pms.xpm,
            pms.net_worth,
            pms.creeps_last_hits,
            pms.creeps_denies,
            pms.opendota_hero_id,
            pms.win
        ';

        if ($matchesLimit === null) {
            return "
                limited_matches AS (
                    SELECT 
                        {$matchStatsSelect}
                    FROM player_match_stats pms
                    WHERE {$whereClause}
                      AND pms.player_id IN ({$eligiblePlayersFilter})
                )
            ";
        }

        return "
            limited_team_matches AS (
                SELECT
                    pms.match_id,
                    pms.opendota_team_id,
                    any(pms.start_time) AS start_time
                FROM player_match_stats pms
                WHERE {$whereClause}
                  AND pms.player_id IN ({$eligiblePlayersFilter})
                GROUP BY pms.match_id, pms.opendota_team_id
                ORDER BY start_time DESC
                LIMIT {$matchesLimit} BY opendota_team_id
            ),
            limited_matches AS (
                SELECT 
                    {$matchStatsSelect}
                FROM player_match_stats pms
                INNER JOIN limited_team_matches ltm
                    ON pms.match_id = ltm.match_id
                   AND pms.opendota_team_id = ltm.opendota_team_id
                WHERE {$whereClause}
                  AND pms.player_id IN ({$eligiblePlayersFilter})
            )
        ";
    }

    /**
     * Топ-метрики связки игроков: сумма avg по метрикам + квоты эмблем role group.
     *
     * @param PlayerInCombo[] $players
     * @return MetricContribution[]
     */
    private function buildComboTopMetrics(array $players, RoleGroup $roleGroup): array
    {
        $sums = array_fill_keys(self::FANTASY_METRICS, 0.0);
        $combinedTotalScore = 0.0;

        foreach ($players as $player) {
            $combinedTotalScore += $player->getAvgTotalScore();

            foreach ($player->getMetrics() as $metric) {
                $sums[$metric->getMetricName()] += $metric->getAvgValue();
            }
        }

        $metricContributions = [];
        foreach ($sums as $metric => $avgValue) {
            $metricContributions[] = [
                'metric' => $metric,
                'avg_value' => $avgValue,
                'contribution' => $combinedTotalScore > 0
                    ? abs(($avgValue / $combinedTotalScore) * 100)
                    : 0,
            ];
        }

        return $this->selectTopMetricsByEmblemRules->select($roleGroup, $metricContributions);
    }

    /**
     * @return PlayerMetrics[]
     */
    private function buildPlayerMetrics(array $row, int $role): array
    {
        $avgTotalScore = (float) $row['avg_total_score'];

        $metricContributions = [];
        foreach (self::FANTASY_METRICS as $metric) {
            $avgValue = (float) $row["avg_{$metric}"];
            $metricContributions[] = [
                'metric' => $metric,
                'avg_value' => $avgValue,
                'contribution' => $avgTotalScore > 0
                    ? abs(($avgValue / $avgTotalScore) * 100)
                    : 0,
            ];
        }

        $topMetricNames = array_map(
            fn(MetricContribution $metric) => $metric->getMetricName(),
            $this->selectTopMetricsByEmblemRules->select(
                RoleGroup::fromRole($role),
                $metricContributions
            )
        );

        $allMetrics = [];
        foreach (self::FANTASY_METRICS as $metric) {
            $allMetrics[] = new PlayerMetrics(
                metricName: $metric,
                avgValue: (float) $row["avg_{$metric}"],
                isTopMetric: in_array($metric, $topMetricNames, true),
            );
        }

        usort($allMetrics, fn($a, $b) => $b->getAvgValue() <=> $a->getAvgValue());

        return $allMetrics;
    }
}
