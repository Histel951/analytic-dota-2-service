<?php
declare(strict_types=1);

namespace App\Application\Fantasy\UseCase;

use App\Application\Fantasy\Dto\GetTopFantasyMetricsQuery;
use App\Application\Fantasy\Dto\GetTopMetricsServiceRequest;
use App\Application\Fantasy\Service\GetTopFantasyMetricsService;
use DateTimeImmutable;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidPlayerIdException;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidTeamIdException;
use Histel951\Dota2Api\Domain\Common\ValueObjects\PlayerId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;

final readonly class GetTopFantasyMetricsUseCase
{
    public function __construct(
        private GetTopFantasyMetricsService $service,
    ) {}

    /**
     * @param GetTopFantasyMetricsQuery $query
     * @return array
     * @throws InvalidTeamIdException
     * @throws InvalidPlayerIdException
     * @throws \DateMalformedStringException
     */
    public function execute(GetTopFantasyMetricsQuery $query): array
    {
        $teamIdsVO = null;
        if (!empty($query->teamIds)) {
            $teamIdsVO = array_map(/**
             * @throws InvalidTeamIdException
             */ fn($id) => new TeamId($id), $query->teamIds);
        }

        $playerIdsVO = null;
        if (!empty($query->playerIds)) {
            $playerIdsVO = array_map(/**
             * @throws InvalidPlayerIdException
             */ fn($id) => new PlayerId($id), $query->playerIds);
        }
        
        $dateFromVO = $query->dateFrom !== null ? new DateTimeImmutable($query->dateFrom) : null;
        $dateToVO = $query->dateTo !== null ? new DateTimeImmutable($query->dateTo) : null;

        $serviceRequest = new GetTopMetricsServiceRequest(
            teamIds: $teamIdsVO,
            playerIds: $playerIdsVO,
            minMatchesPerPlayer: $query->minMatchesPerPlayer,
            dateFrom: $dateFromVO,
            dateTo: $dateToVO,
            matchesLimit: $query->matchesLimit,
        );

        $result = $this->service->getTopMetrics($serviceRequest);

        return $result->toArray();
    }
}
