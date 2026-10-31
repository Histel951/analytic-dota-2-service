<?php

namespace App\Ingestion\Domain\Port;

use App\Ingestion\Domain\Match\MatchPlayers;

interface MatchPlayersRepository
{
    public function saveMatchPlayer(MatchPlayers $entity): void;

    /**
     * @param MatchPlayers[] $players
     * @return void
     */
    public function saveMatchPlayersBulk(array $players): void;

    /**
     * Получить никнеймы игроков по их ID
     * 
     * @param int[] $playerIds
     * @return array<int, string> Массив [player_id => nickname]
     */
    public function getNicknamesByIds(array $playerIds): array;
}