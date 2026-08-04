<?php
declare(strict_types=1);

namespace App\Application\Match\Handler;

use App\Application\Match\Message\MatchFailedMessage;
use App\Application\Match\Message\MatchFantasyCalculateMessage;
use App\Domain\Match\Repository\AnalyticMatchRepository;
use App\Domain\Match\Repository\FantasyStatsRepository;
use App\Domain\Match\Repository\MatchProcessingRepository;
use App\Domain\Match\Service\FantasyScoreCalculator;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
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
