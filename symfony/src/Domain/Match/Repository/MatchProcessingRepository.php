<?php

namespace App\Domain\Match\Repository;

use App\Domain\Match\Entity\MatchProcessing;
use App\Domain\Match\Enum\MatchProcessingStatus;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;

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