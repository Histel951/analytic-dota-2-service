<?php
namespace App\UI\Console\Match;

use App\Application\Match\Message\ParseMatchMessage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: "parse:kafka:match")]
class ParseMatchKafkaCommand
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    )
    {
    }

    public function __invoke(#[Argument("match ID from dota 2")] int $matchId): int
    {
        $this->messageBus->dispatch(new ParseMatchMessage($matchId));

        return Command::SUCCESS;
    }
}