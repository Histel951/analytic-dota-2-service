<?php
declare(strict_types=1);

namespace App\Fantasy\Application\Service;

use App\Fantasy\Application\Dto\GetTopMetricsServiceRequest;
use App\Fantasy\Application\Dto\MetricDto;
use App\Fantasy\Application\Dto\MetricValueDto;
use App\Fantasy\Application\Dto\PlayerAnalyzedMatchDto;
use App\Fantasy\Application\Dto\PlayerInComboDto;
use App\Fantasy\Application\Dto\PlayerMatchStatisticDto;
use App\Fantasy\Application\Dto\RoleGroupMetricsDto;
use App\Fantasy\Application\Dto\TopComboDto;
use App\Fantasy\Application\Dto\TopFantasyMetricsResult;
use App\Fantasy\Domain\Port\FantasyMetricAnalysisRepository;

final readonly class GetTopFantasyMetricsService
{
    public function __construct(
        private FantasyMetricAnalysisRepository $repository,
    ) {}

    public function getTopMetrics(GetTopMetricsServiceRequest $request): TopFantasyMetricsResult
    {
        $recommendations = $this->repository->getTopMetricsByRoleGroups(
            $request->getTeamIds(),
            $request->getPlayerIds(),
            $request->getMinMatchesPerPlayer(),
            $request->getDateFrom(),
            $request->getDateTo(),
            $request->getMatchesLimit()
        );

        $eligiblePlayerIds = $this->repository->getEligiblePlayerIds(
            $request->getTeamIds(),
            $request->getPlayerIds(),
            $request->getMinMatchesPerPlayer(),
            $request->getDateFrom(),
            $request->getDateTo(),
            $request->getMatchesLimit()
        );

        $eligibleTeamIds = $this->repository->getEligibleTeamIds(
            $request->getTeamIds(),
            $request->getPlayerIds(),
            $request->getMinMatchesPerPlayer(),
            $request->getDateFrom(),
            $request->getDateTo(),
            $request->getMatchesLimit()
        );
        
        $teamIdsArray = $request->getTeamIds() !== null 
            ? array_map(fn($teamId) => $teamId->getValue(), $request->getTeamIds())
            : null;

        $playerIdsArray = !empty($eligiblePlayerIds)
            ? array_map(fn($playerId) => $playerId->getValue(), $eligiblePlayerIds)
            : null;

        $roleGroupsDtos = array_map(
            function ($recommendation) {
                $metricDtos = array_map(
                    fn($metric) => new MetricDto(
                        metricName: $metric->getMetricName(),
                        avgValue: $metric->getAvgValue(),
                        contributionPercent: $metric->getContributionPercent(),
                    ),
                    $recommendation->getTopMetrics()
                );

                // Маппим топ-комбо
                $topCombosDtos = array_map(
                    function ($combo) {
                        $playersDtos = array_map(
                            function ($player) {
                                $topMetrics = [];
                                $allMetrics = [];

                                foreach ($player->getMetrics() as $metric) {
                                    $metricValueDto = new MetricValueDto(
                                        metricName: $metric->getMetricName(),
                                        avgValue: $metric->getAvgValue(),
                                    );

                                    $allMetrics[] = $metricValueDto;

                                    if ($metric->isTopMetric()) {
                                        $topMetrics[] = $metricValueDto;
                                    }
                                }

                                $matchesDtos = array_map(
                                    function ($match) {
                                        $statistic = $match->getStatistic();

                                        return new PlayerAnalyzedMatchDto(
                                            matchId: $match->getMatchId(),
                                            matchDuration: $match->getMatchDuration(),
                                            startTime: $match->getStartTime()->format('Y-m-d H:i:s'),
                                            statistic: new PlayerMatchStatisticDto(
                                                kills: $statistic->getKills(),
                                                deaths: $statistic->getDeaths(),
                                                assists: $statistic->getAssists(),
                                                gpm: $statistic->getGpm(),
                                                xpm: $statistic->getXpm(),
                                                netWorth: $statistic->getNetWorth(),
                                                creepsLastHits: $statistic->getCreepsLastHits(),
                                                creepsDenies: $statistic->getCreepsDenies(),
                                                opendotaHeroId: $statistic->getOpendotaHeroId(),
                                            ),
                                            isWin: $match->isWin(),
                                        );
                                    },
                                    $player->getMatches()
                                );

                                return new PlayerInComboDto(
                                    playerId: (int)$player->getPlayerId()->getValue(),
                                    nickname: $player->getNickname(),
                                    role: $player->getRole(),
                                    avgTotalScore: $player->getAvgTotalScore(),
                                    matches: $matchesDtos,
                                    allMetrics: $allMetrics,
                                    topMetrics: $topMetrics,
                                    matchesCount: $player->getMatchesCount(),
                                );
                            },
                            $combo->getPlayers()
                        );

                        $comboTopMetricsDtos = array_map(
                            fn($metric) => new MetricValueDto(
                                metricName: $metric->getMetricName(),
                                avgValue: $metric->getAvgValue(),
                            ),
                            $combo->getTopMetrics()
                        );

                        return new TopComboDto(
                            teamId: (int)$combo->getTeamId()->getValue(),
                            topMetrics: $comboTopMetricsDtos,
                            players: $playersDtos,
                            avgCombinedScore: $combo->getAvgCombinedScore(),
                            matchesCount: $combo->getMatchesCount(),
                            avgMatchDuration: $combo->getAvgMatchDuration(),
                        );
                    },
                    $recommendation->getTopCombos()
                );

                return new RoleGroupMetricsDto(
                    group: $recommendation->getRoleGroup()->value,
                    groupLabel: $recommendation->getRoleGroup()->getLabel(),
                    roles: $recommendation->getRoleGroup()->getRoles(),
                    topMetrics: $metricDtos,
                    topCombos: $topCombosDtos,
                    matchesAnalyzed: $recommendation->getMatchesAnalyzed(),
                    playersAnalyzed: $recommendation->getPlayersAnalyzed(),
                );
            },
            $recommendations
        );

        return new TopFantasyMetricsResult(
            teamIds: $teamIdsArray,
            playerIds: $playerIdsArray,
            teamCount: count($eligibleTeamIds),
            playerCount: count($eligiblePlayerIds),
            roleGroups: $roleGroupsDtos,
        );
    }
}
