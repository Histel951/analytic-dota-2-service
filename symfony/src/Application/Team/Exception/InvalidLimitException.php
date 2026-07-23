<?php
declare(strict_types=1);

namespace App\Application\Team\Exception;

use InvalidArgumentException;

final class InvalidLimitException extends InvalidArgumentException
{
    public static function mustBePositive(int $limit): self
    {
        return new self(sprintf('Limit must be positive, got: %d', $limit));
    }
}
