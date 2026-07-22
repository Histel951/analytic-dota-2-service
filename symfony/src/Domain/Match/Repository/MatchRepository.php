<?php

namespace App\Domain\Match\Repository;

use App\Domain\Match\Entity\MatchEntity;

interface MatchRepository
{
    public function save(MatchEntity $entity): void;
}