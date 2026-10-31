<?php

namespace App\Domain\Match\Repository;

use App\Domain\Match\Entity\MatchProcessing;
use App\Domain\Match\Enum\MatchProcessingStatus;
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