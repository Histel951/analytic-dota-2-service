<?php
declare(strict_types=1);

namespace App\Fantasy\Domain\Analysis;

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

    /**
     * Квоты эмблем для топ-метрик role group.
     *
     * @return array<string, int> EmblemType->value => quota
     */
    public function getEmblemQuota(): array
    {
        return match ($this) {
            // Carry + Offlaner: 2 красные, 1 зелёная
            self::CORE_SIDE => [
                EmblemType::CORE->value => 2,
                EmblemType::UTILITY->value => 1,
            ],
            // Mid: 1 красная, 1 зелёная, 1 синяя
            self::MID => [
                EmblemType::CORE->value => 1,
                EmblemType::UTILITY->value => 1,
                EmblemType::SUPPORT->value => 1,
            ],
            // Support: 2 синие, 1 зелёная
            self::SUPPORT => [
                EmblemType::SUPPORT->value => 2,
                EmblemType::UTILITY->value => 1,
            ],
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
