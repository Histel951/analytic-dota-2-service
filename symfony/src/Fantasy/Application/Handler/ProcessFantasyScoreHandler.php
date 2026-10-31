<?php
declare(strict_types=1);

namespace App\Fantasy\Application\Handler;

use App\Ingestion\Application\Message\MatchFailedMessage;
use App\Ingestion\Application\Message\MatchFantasyCalculateMessage;
use App\Ingestion\Domain\Port\AnalyticMatchRepository;
use App\Fantasy\Domain\Port\FantasyStatsRepository;
use App\Ingestion\Domain\Port\MatchProcessingRepository;
use App\Fantasy\Domain\Scoring\FantasyScoreCalculator;
use App\Shared\Domain\MatchId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

#[AsMessageHandler]
final readonly class ProcessFantasyScoreHandler
{
    public function __construct(
        private FantasyScoreCalculator $calculator,
        private AnalyticMatchRepository $analyticMatchRepository,
        private FantasyStatsRepository $fantasyStatsRepository,
        private MatchProcessingRepository $processingRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(MatchFantasyCalculateMessage $message): void
    {
        $matchId = new MatchId($message->getMatchId());

        try {
            $this->logger->info('Starting fantasy score calculation', [
                'match_id' => $message->getMatchId(),
            ]);

            $processing = $this->processingRepository->findByMatchId($matchId);
            if ($processing !== null) {
                $processing = $processing->markProcessingFantasy();
                $this->processingRepository->update($processing);
            }

            $playerStats = $this->analyticMatchRepository->getPlayerStatsByMatchId($matchId);

            if (empty($playerStats)) {
                $this->logger->warning('No player stats found for match', [
                    'match_id' => $message->getMatchId(),
                ]);
                return;
            }

            $fantasyStats = [];
            foreach ($playerStats as $stats) {
                $score = $this->calculator->calculate($stats);
                $fantasyStats[] = [
                    'matchId' => $stats->getMatchId(),
                    'playerId' => $stats->getPlayerId(),
                    'score' => $score,
                ];
            }

            $this->fantasyStatsRepository->saveBulk($fantasyStats);

            $processing = $this->processingRepository->findByMatchId($matchId);
            if ($processing !== null) {
                $processing = $processing->markFantasyProcessed()->markAsCompleted();
                $this->processingRepository->update($processing);
            }

            $this->logger->info('Fantasy score calculation completed successfully', [
                'match_id' => $message->getMatchId(),
                'players_processed' => count($playerStats),
            ]);

        } catch (Throwable $e) {
            $this->logger->error('Failed to calculate fantasy scores', [
                'match_id' => $message->getMatchId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
                    errorType: 'fantasy_calculation_error'
                )
            );
        }
    }
}
