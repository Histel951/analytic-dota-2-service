<?php

declare(strict_types=1);

namespace App\Domain\Match\Source;

use App\Domain\Match\Entity\ImportedMatch;
use App\Shared\Domain\MatchId;

/**
 * Внешний источник деталей матча.
 */
interface MatchSource
{
    /**
     * @throws MatchSourceException
     */
    public function fetch(MatchId $matchId): ImportedMatch;
}
