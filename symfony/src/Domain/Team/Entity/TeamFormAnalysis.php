<?php
declare(strict_types=1);

namespace App\Domain\Team\Entity;

use App\Domain\Team\Enum\TeamForm;
use App\Domain\Team\ValueObjects\Winrate;
use DateTimeImmutable;
use Histel951\Dota2Api\Domain\Common\ValueObjects\MatchId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Region;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamName;

final readonly class TeamFormAnalysis
{
    /**
     * @param TeamId $teamId
     * @param TeamName $teamName
     * @param TeamForm $form
     * @param Winrate $winrate
     * @param Region $region
     * @param MatchId[] $matchesAnalyzed
     * @param DateTimeImmutable $lastMatchTime
     * @param DateTimeImmutable $analyzedAt
     */
    public function __construct(
        private TeamId $teamId,
        private TeamName $teamName,
        private TeamForm $form,
        private Winrate $winrate,
        private Region $region,
        private array $matchesAnalyzed,
        private DateTimeImmutable $lastMatchTime,
        private DateTimeImmutable $analyzedAt,
    ) {}

    public function getTeamId(): TeamId
    {
        return $this->teamId;
    }

    public function getTeamName(): TeamName
    {
        return $this->teamName;
    }

    public function getForm(): TeamForm
    {
        return $this->form;
    }

    public function getWinrate(): Winrate
    {
        return $this->winrate;
    }

    public function getRegion(): Region
    {
        return $this->region;
    }

    /**
     * @return MatchId[]
     */
    public function getMatchesAnalyzed(): array
    {
        return $this->matchesAnalyzed;
    }

    public function getMatchesCount(): int
    {
        return count($this->matchesAnalyzed);
    }

    public function getLastMatchTime(): DateTimeImmutable
    {
        return $this->lastMatchTime;
    }

    public function getAnalyzedAt(): DateTimeImmutable
    {
        return $this->analyzedAt;
    }
}
