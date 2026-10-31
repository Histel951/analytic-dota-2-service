<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class InvalidRegionException extends DomainException
{
    public static function invalidRegionId(int $regionId): self
    {
        return new self(sprintf('Invalid region ID: %d', $regionId));
    }

    public static function emptyName(): self
    {
        return new self('Region name cannot be empty');
    }

    public static function emptyCode(): self
    {
        return new self('Region code cannot be empty');
    }
}
