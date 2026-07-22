<?php
declare(strict_types=1);

namespace App\Application\Match\Handler;

use App\Application\Match\Message\MatchFailedKafkaMessage;
use App\Application\Match\Message\MatchFailedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class MatchFailedHandler
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(MatchFailedMessage $message): void
    {
        $this->logger->warning('Match processing failed', [
            'match_id' => $message->getMatchId(),
            'error_type' => $message->getErrorType(),
            'error_message' => $message->getErrorMessage(),
        ]);

        $this->messageBus->dispatch(
            new MatchFailedKafkaMessage(
                matchId: $message->getMatchId(),
                errorType: $message->getErrorType(),
                errorMessage: $message->getErrorMessage(),
                timestamp: time(),
            )
        );
    }
}
