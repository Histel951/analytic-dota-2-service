<?php
declare(strict_types=1);

namespace App\Infrastructure\Kafka\Serializer;

use App\Application\Match\Message\MatchFailedKafkaMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class MatchFailedKafkaSerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        throw new \LogicException('This serializer is only for encoding, not decoding');
    }

    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();

        if (!$message instanceof MatchFailedKafkaMessage) {
            throw new \InvalidArgumentException(sprintf(
                'This serializer only supports %s, got %s',
                MatchFailedKafkaMessage::class,
                get_class($message)
            ));
        }

        return [
            'body' => json_encode($message->toArray()),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ];
    }
}
