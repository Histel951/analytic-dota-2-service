<?php

declare(strict_types=1);

namespace App\Domain\Team\Source;

use App\Domain\Team\Entity\TeamMatchRef;
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
