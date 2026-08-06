<?php
declare(strict_types=1);

namespace App\Domain\Fantasy\Enum;

enum RoleGroup: string
{
    case CORE_SIDE = 'core_side';
    case MID = 'mid';
    case SUPPORT = 'support';

    /**
     * @return int[]
     */
    public function getRoles(): array
    {
        return match ($this) {
            self::CORE_SIDE => [1, 3],
            self::MID => [2],
            self::SUPPORT => [4, 5],
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::CORE_SIDE => 'Core Side (Carry + Offlaner)',
            self::MID => 'Mid',
            self::SUPPORT => 'Support (Soft + Hard)',
        };
    }

    public static function fromRole(int $role): self
    {
        return match ($role) {
            1, 3 => self::CORE_SIDE,
            2 => self::MID,
            4, 5 => self::SUPPORT,
            default => throw new \InvalidArgumentException("Invalid role: {$role}"),
        };
    }
}
