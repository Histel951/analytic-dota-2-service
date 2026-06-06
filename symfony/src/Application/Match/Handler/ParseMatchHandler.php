<?php

declare(strict_types=1);

namespace App\Application\Match\Handler;

use App\Application\Match\Extractor\PlayerMatchExtractor;
use App\Application\Match\Message\ParseMatchMessage;
use App\Application\Match\Services\MatchIngestionService;
use Histel951\Dota2Api\Client\Dota2ApiClient;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ParseMatchHandler
{
    public function __construct(
        private PlayerMatchExtractor $extractor,
        private Dota2ApiClient $client,
        private MatchIngestionService $matchIngestionService,
        )
    {
        
    }

    /**
     * @throws InvalidExstarctor
     * 
     */
    public function __invoke(ParseMatchMessage $message): void
    {
        $match = $this->client->matches()->getMatch(new MatchId($message->getMatchId()));
        $matchData = $match->getMatchDetail();
        
        $this->matchIngestionService->ingestMatch($matchData);
    }
}