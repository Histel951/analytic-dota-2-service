<?php
declare(strict_types=1);

namespace App\Ingestion\Application\Exception;

use InvalidArgumentException;

final class InvalidTeamIdException extends InvalidArgumentException
{
    public static function mustBePositive(int $teamId): self
    {
        return new self(sprintf('Team ID must be positive, got: %d', $teamId));
    }
}
