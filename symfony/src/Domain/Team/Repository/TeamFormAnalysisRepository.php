<?php
declare(strict_types=1);

namespace App\Domain\Team\Repository;

use App\Domain\Team\Entity\TeamFormAnalysis;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;

interface TeamFormAnalysisRepository
{
    public function save(TeamFormAnalysis $analysis): void;

    public function findByTeamId(TeamId $teamId): ?TeamFormAnalysis;

    /**
     * @return TeamFormAnalysis[]
     */
    public function findAll(int $limit = 100, int $offset = 0): array;

    /**
     * @return TeamFormAnalysis[]
     */
    public function findFavorites(int $limit = 10): array;

    /**
     * @return TeamFormAnalysis[]
     */
    public function findUnderdogs(int $limit = 10): array;
}
