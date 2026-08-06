<?php
declare(strict_types=1);

namespace App\Application\Fantasy\Service;

use App\Application\Fantasy\Dto\GetTopMetricsServiceRequest;
use App\Application\Fantasy\Dto\MetricDto;
use App\Application\Fantasy\Dto\RoleGroupMetricsDto;
use App\Application\Fantasy\Dto\TopFantasyMetricsResult;
use App\Domain\Fantasy\Repository\FantasyMetricAnalysisRepository;

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

                return new RoleGroupMetricsDto(
                    group: $recommendation->getRoleGroup()->value,
                    groupLabel: $recommendation->getRoleGroup()->getLabel(),
                    roles: $recommendation->getRoleGroup()->getRoles(),
                    topMetrics: $metricDtos,
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
