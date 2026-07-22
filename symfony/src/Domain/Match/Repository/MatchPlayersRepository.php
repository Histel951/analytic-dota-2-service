<?php

namespace App\Domain\Match\Repository;

use App\Domain\Match\Entity\MatchPlayers;

interface MatchPlayersRepository
{
    public function saveMatchPlayer(MatchPlayers $entity): void;

    /**
     * @param MatchPlayers[] $players
     * @return void
     */
    public function saveMatchPlayersBulk(array $players): void;
}