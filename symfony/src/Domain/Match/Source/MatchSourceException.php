<?php

declare(strict_types=1);

namespace App\Domain\Match\Source;

use RuntimeException;

/**
 * Источник не смог отдать матч: ошибка запроса или данных источника.
 */
final class MatchSourceException extends RuntimeException
{
}
