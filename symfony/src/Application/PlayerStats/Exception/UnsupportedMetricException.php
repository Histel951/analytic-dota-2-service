<?php
declare(strict_types=1);

namespace App\Application\PlayerStats\Exception;

use RuntimeException;

final class UnsupportedMetricException extends RuntimeException
{
    public function __construct(string $metric)
    {
        parent::__construct(sprintf('Unsupported metric: %s', $metric));
    }
}
