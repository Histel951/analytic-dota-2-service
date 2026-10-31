<?php

declare(strict_types=1);

namespace App\Ingestion\Domain\Port;

use RuntimeException;

/**
 * Источник не смог отдать матч: ошибка запроса или данных источника.
 */
final class MatchSourceException extends RuntimeException
{
}
