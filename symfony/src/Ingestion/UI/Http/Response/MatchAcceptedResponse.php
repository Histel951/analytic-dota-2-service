<?php
declare(strict_types=1);

namespace App\Ingestion\UI\Http\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class MatchAcceptedResponse extends JsonResponse
{
    public function __construct(int $matchId)
    {
        parent::__construct([
            'message' => 'Match accepted for processing',
            'match_id' => $matchId,
        ], Response::HTTP_ACCEPTED);
    }
}
