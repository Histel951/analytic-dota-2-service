<?php
declare(strict_types=1);

namespace App\Application\PlayerStats\Exception;

use InvalidArgumentException;

final class InvalidPaginationException extends InvalidArgumentException
{
    public static function invalidLimit(int $limit, int $maxLimit): self
    {
        return new self(sprintf('Limit must be between 1 and %d, got: %d', $maxLimit, $limit));
    }

    public static function invalidOffset(int $offset): self
    {
        return new self(sprintf('Offset must be >= 0, got: %d', $offset));
    }
}
