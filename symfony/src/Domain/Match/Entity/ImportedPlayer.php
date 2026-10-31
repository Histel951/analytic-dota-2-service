<?php

declare(strict_types=1);

namespace App\Domain\Match\Entity;

use App\Shared\Domain\PlayerId;

/**
 * Игрок импортированного матча: идентичность + статистика.
 */
final readonly class ImportedPlayer
{
    public function __construct(
        private PlayerId $playerId,
        private ?string $proName,
        private PlayerMatchStats $stats,
    ) {
    }

    public function getPlayerId(): PlayerId
    {
        return $this->playerId;
    }

    public function getProName(): ?string
    {
        return $this->proName;
    }

    public function getStats(): PlayerMatchStats
    {
        return $this->stats;
    }
}
