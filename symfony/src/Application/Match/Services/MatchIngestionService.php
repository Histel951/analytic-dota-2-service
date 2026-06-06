<?php

declare(strict_types=1);

namespace App\Application\Match\Services;

use App\Domain\Match\Repository\MatchRepository;
use App\Infrastructure\Kafka\Producer\MatchProducer;
use App\Application\Match\Extractor\PlayerMatchExtractor;

final readonly class MatchIngestionService
{
    public function __construct(
        private MatchRepository $matchRepository,
        private MatchProducer $matchProducer,
        private PlayerMatchExtractor $playerExtractor
    ) {}

    public function ingestMatch(array $matchData): void
    {
        $this->matchRepository->save($matchData);
        
        $playersStats = $this->playerExtractor->extract($matchData);
        
        $this->matchProducer->send($playersStats);
    }
}