<?php
declare(strict_types=1);

namespace App\Application\Team\Exception;

use Exception;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;

final class InsufficientMatchesException extends Exception
{
    public static function forTeam(TeamId $teamId, int $matchesFound, int $minimumRequired): self
    {
        return new self(
            sprintf(
                'Team %d has insufficient matches for analysis: %d found, minimum %d required',
                $teamId->getValue(),
                $matchesFound,
                $minimumRequired
            )
        );
    }
}
