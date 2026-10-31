<?php

declare(strict_types=1);

namespace App\Ingestion\Domain\Port;

use App\Ingestion\Domain\Team\TeamMatchRef;
use App\Shared\Domain\TeamId;

/**
 * Внешний источник истории матчей команды.
 */
interface TeamMatchesSource
{
    /**
     * Матчи в порядке, в котором их отдаёт источник.
     *
     * @return list<TeamMatchRef>
     *
     * @throws TeamMatchesSourceException
     */
    public function listByTeam(TeamId $teamId): array;
}
