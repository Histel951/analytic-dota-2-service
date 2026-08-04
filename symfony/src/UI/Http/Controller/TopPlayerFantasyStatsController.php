<?php

namespace App\UI\Http\Controller;

use App\Application\PlayerStats\Dto\GetTopFantasyPlayersQuery;
use App\Application\PlayerStats\UseCase\GetTopFantasyPlayersUseCase;
use App\Infrastructure\Http\Response\TopFantasyPlayersResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class PlayerFantasyStatsController extends AbstractController
{
    #[Route('/api/players/fantasy/top', name: 'api_players_fantasy_top', methods: ['GET'])]
    public function top(
        Request $request,
        ValidatorInterface $validator,
        GetTopFantasyPlayersUseCase $useCase
    ): TopFantasyPlayersResponse {
        $query = new GetTopFantasyPlayersQuery(
            limit: (int) $request->query->get('limit', 10),
            matches: (int) $request->query->get('matches', 20),
            offset: (int) $request->query->get('offset', 0),
        );

        $violations = $validator->validate($query);
        if (count($violations) > 0) {
            throw new ValidationFailedException($query, $violations);
        }

        return new TopFantasyPlayersResponse(
            $useCase->execute($query->limit, $query->matches, $query->offset)
        );
    }
}