<?php
declare(strict_types=1);

namespace App\Ingestion\Application\UseCase;

use App\Ingestion\Application\Message\ParseMatchMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ParseMatchUseCase
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {}

    public function execute(int $matchId): void
    {
        $this->messageBus->dispatch(new ParseMatchMessage($matchId));
    }
}
