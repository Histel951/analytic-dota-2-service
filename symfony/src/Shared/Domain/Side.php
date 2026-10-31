<?php

declare(strict_types=1);

namespace App\Shared\Domain;

enum Side: int
{
    case RADIANT = 0;
    case DIRE = 1;
}
