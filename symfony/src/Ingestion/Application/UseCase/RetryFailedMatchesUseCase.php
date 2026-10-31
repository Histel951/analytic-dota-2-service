<?php
declare(strict_types=1);

namespace App\Ingestion\Application\UseCase;

use App\Ingestion\Application\Message\ParseMatchMessage;
use App\Ingestion\Domain\Processing\MatchProcessingStatus;
use App\Ingestion\Domain\Port\MatchProcessingRepository;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class RetryFailedMatchesUseCase
{
    public function __construct(
        private MatchProcessingRepository $matchProcessingRepository,
        private MessageBusInterface $messageBus,
    ) {}

    /**
     * @return array{total: int, match_ids: int[]}
     */
    public function execute(): array
    {
        $failedMatches = $this->matchProcessingRepository->findByStatus(
            MatchProcessingStatus::FAILED
        );

        $matchIds = [];
        foreach ($failedMatches as $matchProcessing) {
            $matchId = $matchProcessing->getMatchId()->getValue();
            $matchIds[] = $matchId;
            
            // Отправляем матч на повторную обработку
            $this->messageBus->dispatch(new ParseMatchMessage($matchId));
        }

        return [
            'total' => count($matchIds),
            'match_ids' => $matchIds,
        ];
    }
}
