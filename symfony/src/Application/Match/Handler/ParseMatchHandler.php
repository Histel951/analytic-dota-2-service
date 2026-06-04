<?php
declare(strict_types = 1);

namespace App\Application\Match\Handler;

use App\Application\Match\Extractor\PlayerMatchExtractor;
use App\Application\Match\Message\ParseMatchMessage;
use App\Domain\Match\Repository\AnalyticMatchRepository;
use App\Domain\Match\Repository\MatchRepository;
use Histel951\Dota2Api\Client\Dota2ApiClient;
use Histel951\Dota2Api\Domain\Common\Exceptions\InvalidMatchIdException;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ParseMatchHandler
{
    public function __construct(
        private PlayerMatchExtractor    $extractor,
        private Dota2ApiClient          $client,
        private AnalyticMatchRepository $analyticMatchRepository,
        private MatchRepository $matchRepository,
    )
    {
    }

    /**
     * @throws InvalidMatchIdException
     */
    public function __invoke(ParseMatchMessage $message): void
    {
        $match = $this->client->matches()->getMatch(new MatchId($message->getMatchId()));
        $playersMatchData = $this->extractor->extract($match->getMatchDetail());

        foreach ($playersMatchData as $playerMatchData) {
            $this->analyticMatchRepository->insertMatchStats($playerMatchData);
        }

        // todo: придумать чё добавлять по матчам в бд
        $this->matchRepository->insert();
    }
}