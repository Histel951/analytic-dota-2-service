<?php
declare(strict_types=1);

namespace App\Infrastructure\Kafka\Producer;

use App\Application\Match\Message\ParseMatchMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MatchProducer
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {}

    public function dispatch(int $matchId): void
    {
        $this->bus->dispatch(
            new ParseMatchMessage($matchId)
        );
    }
}