<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class TopFantasyPlayersResponse extends JsonResponse
{
    public function __construct(array $players)
    {
        parent::__construct($players, Response::HTTP_OK);
    }
}
