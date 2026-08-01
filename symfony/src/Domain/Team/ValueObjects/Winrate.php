<?php
declare(strict_types=1);

namespace App\Domain\Team\ValueObjects;

use InvalidArgumentException;

final readonly class Winrate
{
    public function __construct(
        private float $value,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->value < 0.0 || $this->value > 100.0) {
            throw new InvalidArgumentException(
                sprintf('Winrate must be between 0 and 100, got %.2f', $this->value)
            );
        }
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getPercentage(): string
    {
        return sprintf('%.2f%%', $this->value);
    }

    public static function fromMatchResults(int $wins, int $total): self
    {
        if ($total === 0) {
            return new self(0.0);
        }

        $winrate = ($wins / $total) * 100;
        return new self($winrate);
    }
}
