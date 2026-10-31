<?php

namespace App\Ingestion\Infrastructure\Kafka;

final readonly class KafkaTopicsConfig
{
    public function __construct(
        public string $matchesParse,
        public string $matchesFailed,
        public string $matchesAnalytics,
        public string $playersAggregate,
        public string $fantasyCalculate,
    ) {}
}