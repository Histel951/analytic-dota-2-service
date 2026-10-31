<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Роль (позиция) игрока в матче: 1 — керри, 2 — мид, 3 — оффлейн, 4 — саппорт, 5 — хард-саппорт.
 */
enum PlayerRole: int
{
    case UNKNOWN = 0;
    case CARRY = 1;
    case MIDDLE = 2;
    case OFFLANE = 3;
    case SUPPORT = 4;
    case HARD_SUPPORT = 5;
}
