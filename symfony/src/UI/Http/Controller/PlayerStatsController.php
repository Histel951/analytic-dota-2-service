<?php
declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\PlayerStats\Dto\GetTopPlayersQuery;
use App\Application\PlayerStats\UseCase\GetTopPlayersByMetricUseCase;
use App\Infrastructure\Http\Response\TopPlayersResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class PlayerStatsController extends AbstractController
{
    #[Route('/api/players/top', name: 'api_players_top', methods: ['GET'])]
    public function top(
        Request $request,
        ValidatorInterface $validator,
        GetTopPlayersByMetricUseCase $useCase
    ): TopPlayersResponse
    {
        $query = new GetTopPlayersQuery(
            metric: $request->query->get('metric', ''),
            limit: (int) $request->query->get('limit', 50),
            offset: (int) $request->query->get('offset', 0),
        );

        $violations = $validator->validate($query);
        
        if (count($violations) > 0) {
            throw new ValidationFailedException($query, $violations);
        }

        $players = $useCase->execute(
            $query->metric,
            $query->limit,
            $query->offset
        );

        return new TopPlayersResponse($players);
    }
}
