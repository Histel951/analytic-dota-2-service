<?php
declare(strict_types=1);

namespace App\Application\Match\Handler;

use App\Application\Match\Message\MatchFailedMessage;
use App\Application\Match\Message\MatchFetchedMessage;
use App\Application\Match\Message\ParseMatchMessage;
use App\Domain\Match\Entity\MatchProcessing;
use App\Domain\Match\Enum\MatchProcessingStatus;
use App\Domain\Match\Repository\MatchProcessingRepository;
use App\Domain\Match\Source\MatchSource;
use App\Shared\Domain\Exception\InvalidMatchIdException;
use App\Shared\Domain\MatchId;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

#[AsMessageHandler]
final readonly class FetchMatchFromApiHandler
{
    public function __construct(
        private MatchSource $matchSource,
        private MatchProcessingRepository $processingRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ParseMatchMessage $message): void
    {
        $matchId = new MatchId($message->getMatchId());
        
        $processing = $this->processingRepository->findByMatchId($matchId);
        if ($processing !== null && $processing->isCompleted()) {
            $this->logger->info('Match already processed, skipping', [
                'match_id' => $message->getMatchId(),
                'status' => $processing->getStatus()->value,
            ]);
            return;
        }

        if ($processing === null) {
            $processing = new MatchProcessing(
                matchId: $matchId,
                parsedAt: new DateTimeImmutable(),
                status: MatchProcessingStatus::PENDING
            );
            $this->processingRepository->save($processing);
        }

        try {
            $processing = $processing->markAsFetched();
            $this->processingRepository->update($processing);

            $match = $this->matchSource->fetch($matchId);

            $this->messageBus->dispatch(
                MatchFetchedMessage::fromMatch(
                    $message->getMatchId(),
                    $match
                )
            );

            $this->logger->info('Match fetched successfully', [
                'match_id' => $message->getMatchId(),
            ]);

        } catch (InvalidMatchIdException $e) {
            $this->handleError($matchId, $e, 'invalid_match_id');
        } catch (Throwable $e) {
            $this->handleError($matchId, $e, 'fetch_error');
        }
    }

    private function handleError(MatchId $matchId, Throwable $error, string $errorType): void
    {
        $this->logger->error('Failed to fetch match ' . $matchId->getValue(), [
            'match_id' => $matchId->getValue(),
            'error' => $error->getMessage(),
            'error_type' => $errorType,
        ]);

        $processing = $this->processingRepository->findByMatchId($matchId);
        if ($processing !== null) {
            $processing = $processing->markAsFailed();
            $this->processingRepository->update($processing);
        }

        $this->messageBus->dispatch(
            new MatchFailedMessage(
                matchId: $matchId->getValue(),
                errorMessage: $error->getMessage(),
                errorType: $errorType
            )
        );
    }
}
