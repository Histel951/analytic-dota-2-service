<?php

declare(strict_types=1);

namespace App\Ingestion\Domain\Port;

use App\Ingestion\Domain\Match\ImportedMatch;
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
