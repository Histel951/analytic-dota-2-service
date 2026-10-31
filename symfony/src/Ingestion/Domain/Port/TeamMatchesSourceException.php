<?php

declare(strict_types=1);

namespace App\Ingestion\Domain\Port;

use RuntimeException;

/**
 * Источник не смог отдать историю матчей команды.
 */
final class TeamMatchesSourceException extends RuntimeException
{
}
