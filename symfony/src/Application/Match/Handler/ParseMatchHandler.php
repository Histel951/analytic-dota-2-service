<?php
declare(strict_types = 1);

namespace App\Application\Match\Handler;

use App\Application\Match\Extractor\PlayerMatchExtractor;
use App\Application\Match\Message\ParseMatchMessage;
use App\Domain\Match\Entity\MatchEntity;
use App\Domain\Match\Entity\MatchProcessing;
use App\Domain\Match\Enum\MatchProcessingStatus;
use App\Domain\Match\Repository\AnalyticMatchRepository;
use App\Domain\Match\Repository\MatchRepository;
use DateTimeImmutable;
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
        $matchId = new MatchId($message->getMatchId());
        $match = $this->client->matches()->getMatch($matchId);
        $fetchedAt = new DateTimeImmutable();
        $playersMatchData = $this->extractor->extract($match->getMatchDetail());


        $analyticsProcessedAt = new DateTimeImmutable();
        $this->analyticMatchRepository->insertMultiplyMatchStats($playersMatchData);



        $parsedAt = new DateTimeImmutable();
        $newMatch = new MatchEntity(
            matchId: $matchId,
            startTime: $match->getMatchDetail()->getStartTime()->getValue(),
            duration: $match->getMatchDetail()->getDuration()->getSeconds(),
            radiantWin: $match->getMatchDetail()->getRadiant()->isWon(),

        );
        $this->matchRepository->save($newMatch);

        $matchProcessing = new MatchProcessing(
            matchId: $matchId,
            parsedAt: $parsedAt,
            fetchedAt: $fetchedAt,
            analyticsProcessedAt: $analyticsProcessedAt,
            fantasyProcessedAt: null,
            status: MatchProcessingStatus::COMPLETED
        );
        $this->matchRepository->saveProccessingStatus($matchProcessing);

        foreach ($match->getMatchDetail()->getPlayers()->getValue() as $player) {
            $this->matchRepository->saveMatchPlayer(
                player_id: $player->getIdentity()->getPlayerId(),
                match_id: $match->getMatchId()->getValue(),
                nickname: $player->getIdentity()->getPlayerProName()->getValue()
            );

        }
    }
}