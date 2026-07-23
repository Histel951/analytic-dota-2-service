<?php
declare(strict_types=1);

namespace App\Application\Team\Exception;

use DateTimeImmutable;
use InvalidArgumentException;

final class InvalidDateRangeException extends InvalidArgumentException
{
    public static function dateFromGreaterThanDateTo(
        DateTimeImmutable $dateFrom,
        DateTimeImmutable $dateTo
    ): self {
        return new self(sprintf(
            'Date from (%s) cannot be greater than date to (%s)',
            $dateFrom->format('Y-m-d H:i:s'),
            $dateTo->format('Y-m-d H:i:s')
        ));
    }
}
