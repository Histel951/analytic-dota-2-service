<?php
declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\Fantasy\Dto\GetTopFantasyMetricsQuery;
use App\Application\Fantasy\UseCase\GetTopFantasyMetricsUseCase;
use App\Infrastructure\Http\Response\TopFantasyMetricsResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class FantasyController extends AbstractController
{
    #[Route('/api/fantasy/top-metrics', name: 'api_fantasy_top_metrics', methods: ['GET'])]
    public function topMetrics(
        Request $request,
        ValidatorInterface $validator,
        GetTopFantasyMetricsUseCase $useCase
    ): TopFantasyMetricsResponse
    {
        $teamIdsParam = $request->query->get('team_ids');
        $teamIds = null;
        
        if ($teamIdsParam !== null) {
            $teamIdsArray = array_filter(
                array_map('trim', explode(',', $teamIdsParam)),
                fn($id) => is_numeric($id)
            );
            $teamIds = !empty($teamIdsArray) ? array_map('intval', $teamIdsArray) : null;
        }

        $playerIdsParam = $request->query->get('player_ids');
        $playerIds = null;
        
        if ($playerIdsParam !== null) {
            $playerIdsArray = array_filter(
                array_map('trim', explode(',', $playerIdsParam)),
                fn($id) => is_numeric($id)
            );
            $playerIds = !empty($playerIdsArray) ? array_map('intval', $playerIdsArray) : null;
        }

        $matchesLimitParam = $request->query->get('matches_limit');
        $matchesLimit = $matchesLimitParam !== null ? (int) $matchesLimitParam : null;

        $query = new GetTopFantasyMetricsQuery(
            teamIds: $teamIds,
            playerIds: $playerIds,
            minMatchesPerPlayer: (int) $request->query->get('min_matches_per_player', 10),
            dateFrom: $request->query->get('date_from'),
            dateTo: $request->query->get('date_to'),
            matchesLimit: $matchesLimit,
        );

        $violations = $validator->validate($query);
        
        if (count($violations) > 0) {
            throw new ValidationFailedException($query, $violations);
        }

        $result = $useCase->execute($query);

        return new TopFantasyMetricsResponse($result);
    }
}
