<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class SuccessResponse extends JsonResponse
{
    public function __construct(array $data = [])
    {
        parent::__construct(["data" => $data], Response::HTTP_OK);
    }
}
