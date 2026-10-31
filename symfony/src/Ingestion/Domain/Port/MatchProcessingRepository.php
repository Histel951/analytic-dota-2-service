<?php

namespace App\Ingestion\Domain\Port;

use App\Ingestion\Domain\Processing\MatchProcessing;
use App\Ingestion\Domain\Processing\MatchProcessingStatus;
use App\Shared\Domain\MatchId;

interface MatchProcessingRepository
{
    public function save(MatchProcessing $entity): void;

    public function update(MatchProcessing $entity): void;

    public function findByMatchId(MatchId $matchId): ?MatchProcessing;

    /**
     * @return MatchProcessing[]
     */
    public function findByStatus(MatchProcessingStatus $status): array;
}