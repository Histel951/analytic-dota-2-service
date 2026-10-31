<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenDota;

use App\Domain\Team\Entity\TeamMatchRef;
use App\Domain\Team\Source\TeamMatchesSource;
use App\Domain\Team\Source\TeamMatchesSourceException;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\MatchId;
use App\Shared\Domain\TeamId;
use Histel951\Dota2Api\Client\Dota2ApiClient;
use Histel951\Dota2Api\Exception\Dota2ApiExceptionInterface;
use Histel951\Dota2Api\OpenDota\Team\Domain\History\TeamMatch;
use Histel951\Dota2Api\SharedKernel\TeamId as SourceTeamId;

/**
 * История матчей команды из OpenDota (GET teams/{id}/matches).
 */
final readonly class OpenDotaTeamMatchesSource implements TeamMatchesSource
{
    public function __construct(
        private Dota2ApiClient $client,
    ) {
    }

    public function listByTeam(TeamId $teamId): array
    {
        try {
            $teamMatches = $this->client->openDota()->teams()->getMatchesByTeam(new SourceTeamId($teamId->getValue()));
        } catch (Dota2ApiExceptionInterface $e) {
            throw new TeamMatchesSourceException($e->getMessage(), 0, $e);
        }

        try {
            return array_values(array_map(
                static fn(TeamMatch $match): TeamMatchRef => new TeamMatchRef(
                    matchId: new MatchId($match->getMatchId()->getValue()),
                    startTime: $match->getStartTime(),
                    won: $match->matchOutcome()->isWon(),
                ),
                $teamMatches,
            ));
        } catch (DomainException $e) {
            throw new TeamMatchesSourceException($e->getMessage(), 0, $e);
        }
    }
}
