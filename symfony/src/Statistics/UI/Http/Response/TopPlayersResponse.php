<?php
declare(strict_types=1);

namespace App\Statistics\UI\Http\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class TopPlayersResponse extends JsonResponse
{
    public function __construct(array $players)
    {
        parent::__construct($players, Response::HTTP_OK);
    }
}
