<?php
declare(strict_types=1);

namespace App\Application\Match\Handler;

use App\Application\Match\Message\MatchAnalyticsProcessedMessage;
use App\Application\Match\Message\MatchFailedMessage;
use App\Domain\Match\Entity\MatchEntity;
use App\Domain\Match\Entity\MatchPlayers;
use App\Domain\Match\Repository\MatchPlayersRepository;
use App\Domain\Match\Repository\MatchProcessingRepository;
use App\Domain\Match\Repository\MatchRepository;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Region;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

#[AsMessageHandler]
final readonly class PersistMatchDataHandler
{
    public function __construct(
        private MatchRepository $matchRepository,
        private MatchPlayersRepository $playersRepository,
        private MatchProcessingRepository $processingRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(MatchAnalyticsProcessedMessage $message): void
    {
        $matchId = new MatchId($message->getMatchId());

        try {
            $matchDetail = $message->getMatchDetail();

            $regionId = $matchDetail->getRegion()?->getId();

            $newMatch = new MatchEntity(
                matchId: $matchId,
                startTime: $matchDetail->getStartTime()->getValue(),
                duration: $matchDetail->getDuration()->getSeconds(),
                radiantWin: $matchDetail->getRadiant()->isWon(),
                region: $regionId ? Region::fromId($regionId) : null,
            );
            $this->matchRepository->save($newMatch);

            $players = [];
            foreach ($matchDetail->getPlayers()->getValue() as $player) {
                $matchPlayer = new MatchPlayers(
                    matchId: $matchId,
                    playerId: $player->getIdentity()->getPlayerId(),
                    playerProName: $player->getIdentity()->getPlayerProName()->getValue(),
                );
                $players[] = $matchPlayer;
            }
            $this->playersRepository->saveMatchPlayersBulk($players);

            $processing = $this->processingRepository->findByMatchId($matchId);
            if ($processing !== null) {
                $processing = $processing->markAsCompleted();
                $this->processingRepository->update($processing);
            }

            $this->logger->info('Match data persisted successfully', [
                'match_id' => $message->getMatchId(),
            ]);

        } catch (Throwable $e) {
            $this->logger->error('Failed to persist match data', [
                'match_id' => $message->getMatchId(),
                'error' => $e->getMessage(),
            ]);

            $processing = $this->processingRepository->findByMatchId($matchId);
            if ($processing !== null) {
                $processing = $processing->markAsFailed();
                $this->processingRepository->update($processing);
            }

            $this->messageBus->dispatch(
                new MatchFailedMessage(
                    matchId: $message->getMatchId(),
                    errorMessage: $e->getMessage(),
                    errorType: 'persist_error'
                )
            );
        }
    }
}
