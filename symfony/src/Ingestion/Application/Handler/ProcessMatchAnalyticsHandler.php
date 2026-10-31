<?php
declare(strict_types=1);

namespace App\Ingestion\Application\Handler;

use App\Ingestion\Application\Message\MatchAnalyticsProcessedMessage;
use App\Ingestion\Application\Message\MatchFailedMessage;
use App\Ingestion\Application\Message\MatchFetchedMessage;
use App\Ingestion\Domain\Port\AnalyticMatchRepository;
use App\Ingestion\Domain\Port\MatchProcessingRepository;
use App\Shared\Domain\MatchId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

#[AsMessageHandler]
final readonly class ProcessMatchAnalyticsHandler
{
    public function __construct(
        private AnalyticMatchRepository $analyticMatchRepository,
        private MatchProcessingRepository $processingRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(MatchFetchedMessage $message): void
    {
        $matchId = new MatchId($message->getMatchId());

        try {
            $playersMatchData = $message->getMatch()->getPlayerStats();

            $this->analyticMatchRepository->insertMultiplyMatchStats($playersMatchData);

            $processing = $this->processingRepository->findByMatchId($matchId);
            if ($processing !== null) {
                $processing = $processing->markAnalyticsProcessed();
                $this->processingRepository->update($processing);
            }

            $this->messageBus->dispatch(
                new MatchAnalyticsProcessedMessage(
                    matchId: $message->getMatchId(),
                    serializedMatch: $message->getSerializedMatch()
                )
            );

            $this->logger->info('Match analytics processed successfully', [
                'match_id' => $message->getMatchId(),
            ]);

        } catch (Throwable $e) {
            $this->logger->error('Failed to process match analytics', [
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
                    errorType: 'analytics_error'
                )
            );
        }
    }
}
