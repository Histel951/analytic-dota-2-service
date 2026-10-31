<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenDota;

use App\Domain\Match\Entity\ImportedMatch;
use App\Domain\Match\Source\MatchSource;
use App\Domain\Match\Source\MatchSourceException;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\MatchId;
use Histel951\Dota2Api\Client\Dota2ApiClient;
use Histel951\Dota2Api\Exception\Dota2ApiExceptionInterface;
use Histel951\Dota2Api\SharedKernel\MatchId as SourceMatchId;

/**
 * Детали матча из OpenDota (GET matches/{id}).
 */
final readonly class OpenDotaMatchSource implements MatchSource
{
    public function __construct(
        private Dota2ApiClient $client,
        private OpenDotaMatchMapper $mapper,
    ) {
    }

    public function fetch(MatchId $matchId): ImportedMatch
    {
        try {
            $result = $this->client->openDota()->matches()->getMatch(new SourceMatchId($matchId->getValue()));
        } catch (Dota2ApiExceptionInterface $e) {
            throw new MatchSourceException($e->getMessage(), 0, $e);
        }

        $matchDetail = $result->getMatchDetail();

        if ($matchDetail === null) {
            throw new MatchSourceException(
                'Match detail is null. Match may not exist or API returned incomplete data.',
                0,
                $result->getError(),
            );
        }

        try {
            return $this->mapper->toImportedMatch($matchDetail);
        } catch (DomainException $e) {
            throw new MatchSourceException($e->getMessage(), 0, $e);
        }
    }
}
