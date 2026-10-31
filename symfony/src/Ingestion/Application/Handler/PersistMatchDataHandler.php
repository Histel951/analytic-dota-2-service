<?php
declare(strict_types=1);

namespace App\Ingestion\Application\Handler;

use App\Ingestion\Application\Message\MatchAnalyticsProcessedMessage;
use App\Ingestion\Application\Message\MatchFailedMessage;
use App\Ingestion\Application\Message\MatchFantasyCalculateMessage;
use App\Ingestion\Domain\Match\MatchEntity;
use App\Ingestion\Domain\Match\MatchPlayers;
use App\Ingestion\Domain\Port\MatchPlayersRepository;
use App\Ingestion\Domain\Port\MatchProcessingRepository;
use App\Ingestion\Domain\Port\MatchRepository;
use App\Shared\Domain\MatchId;
use App\Shared\Domain\Region\Region;
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
            $match = $message->getMatch();

            $regionId = $match->getRegion()?->getId();

            $newMatch = new MatchEntity(
                matchId: $matchId,
                startTime: $match->getStartTime(),
                duration: $match->getDurationSeconds(),
                radiantWin: $match->isRadiantWin(),
                region: $regionId ? Region::fromId($regionId) : null,
            );
            $this->matchRepository->save($newMatch);

            $players = [];
            foreach ($match->getPlayers() as $player) {
                $matchPlayer = new MatchPlayers(
                    matchId: $matchId,
                    playerId: $player->getPlayerId(),
                    playerProName: $player->getProName(),
                );
                $players[] = $matchPlayer;
            }
            $this->playersRepository->saveMatchPlayersBulk($players);

            $this->messageBus->dispatch(
                new MatchFantasyCalculateMessage(
                    matchId: $message->getMatchId(),
                    serializedMatch: $message->getSerializedMatch()
                )
            );

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
