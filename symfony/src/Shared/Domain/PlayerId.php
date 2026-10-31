<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use App\Shared\Domain\Exception\InvalidPlayerIdException;

/**
 * Steam ID32 игрока. Значение хранится строкой (как отдаёт getValue()), toInt() — числом.
 */
final readonly class PlayerId
{
    private const int MIN_STEAM_ID = 1;
    private const int MAX_STEAM_ID = 4294967295;

    private string $value;
    private int $intValue;

    /**
     * @throws InvalidPlayerIdException
     */
    public function __construct(string|int $value)
    {
        $normalized = (string) $value;

        $this->validate($normalized);

        $this->value = ltrim($normalized, '0');
        $this->intValue = (int) $this->value;
    }

    /**
     * @throws InvalidPlayerIdException
     */
    private function validate(string $value): void
    {
        if ($value === '') {
            throw new InvalidPlayerIdException('Player ID cannot be empty');
        }

        if (!ctype_digit($value)) {
            throw new InvalidPlayerIdException(
                sprintf('Player ID must be numeric, "%s" given', $value)
            );
        }

        $intValue = (int) $value;

        if ($intValue < self::MIN_STEAM_ID) {
            throw new InvalidPlayerIdException(
                sprintf('Player ID must be at least %d', self::MIN_STEAM_ID)
            );
        }

        if ($intValue > self::MAX_STEAM_ID) {
            throw new InvalidPlayerIdException(
                sprintf('Player ID cannot exceed %d', self::MAX_STEAM_ID)
            );
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function toInt(): int
    {
        return $this->intValue;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
