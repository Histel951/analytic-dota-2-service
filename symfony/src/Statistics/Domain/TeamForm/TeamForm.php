<?php
declare(strict_types=1);

namespace App\Statistics\Domain\TeamForm;

enum TeamForm: string
{
    case PRIME = 'prime';
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';

    public static function fromWinrate(float $winrate): self
    {
        return match (true) {
            $winrate >= 70.0 => self::PRIME,
            $winrate >= 60.0 => self::HIGH,
            $winrate >= 40.0 => self::MEDIUM,
            default => self::LOW,
        };
    }

    public function getNumericValue(): int
    {
        return match ($this) {
            self::PRIME => 1,
            self::HIGH => 2,
            self::MEDIUM => 3,
            self::LOW => 4,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::PRIME => 'Отличная форма',
            self::HIGH => 'Хорошая форма',
            self::MEDIUM => 'Средняя форма',
            self::LOW => 'Плохая форма',
        };
    }
}
