<?php

declare(strict_types=1);

namespace App\Domain\Team\Source;

use RuntimeException;

/**
 * Источник не смог отдать историю матчей команды.
 */
final class TeamMatchesSourceException extends RuntimeException
{
}
