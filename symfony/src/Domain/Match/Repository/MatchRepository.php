<?php

namespace App\Domain\Match\Repository;

use App\Domain\Match\Entity\MatchEntity;
use App\Domain\Match\Entity\MatchPlayers;
use App\Domain\Match\Entity\MatchProcessing;

interface MatchRepository
{
    public function save(MatchEntity $entity): void;

    public function saveProccessingStatus(MatchProcessing $entity): void;
    public function saveMatchPlayer(MatchPlayers $entity): void;

}