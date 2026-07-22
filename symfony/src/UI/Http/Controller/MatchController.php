<?php
declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\Match\Dto\ParseMatchRequest;
use App\Application\Match\UseCase\ParseMatchUseCase;
use App\Infrastructure\Http\Response\MatchAcceptedResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class MatchController extends AbstractController
{
    #[Route('/api/matches/parse', name: 'api_matches_parse', methods: ['POST'])]
    public function parse(
        Request $request,
        ValidatorInterface $validator,
        ParseMatchUseCase $useCase
    ): MatchAcceptedResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $parseRequest = new ParseMatchRequest(
            matchId: (int) ($data['match_id'] ?? 0),
        );

        $violations = $validator->validate($parseRequest);
        
        if (count($violations) > 0) {
            throw new ValidationFailedException($parseRequest, $violations);
        }

        $useCase->execute($parseRequest->matchId);

        return new MatchAcceptedResponse($parseRequest->matchId);
    }
}
