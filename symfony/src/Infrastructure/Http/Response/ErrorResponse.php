<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ErrorResponse extends JsonResponse
{
    public function __construct(string $message, int $statusCode)
    {
        parent::__construct(['error' => $message], $statusCode);
    }
}
