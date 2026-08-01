<?php
declare(strict_types=1);

namespace App\Application\Team\Service;

use App\Application\Team\Exception\InsufficientMatchesException;
use App\Domain\Match\Repository\MatchRepository;
use App\Domain\Team\Entity\TeamFormAnalysis;
use App\Domain\Team\Enum\TeamForm;
use App\Domain\Team\Repository\TeamMatchRepository;
use App\Domain\Team\ValueObjects\Winrate;
use DateTimeImmutable;
use Histel951\Dota2Api\Domain\Common\ValueObjects\Region;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamName;
use Psr\Log\LoggerInterface;

/**
 * Анализатор формы команды на основе данных из БД
 * Не использует OpenDota API
 */
final readonly class TeamFormAnalyzer
{
    private const int MINIMUM_MATCHES = 5;

    public function __construct(
        private TeamMatchRepository $teamMatchRepository,
        private MatchRepository $matchRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws InsufficientMatchesException
     */
    public function analyze(
        TeamId $teamId,
        ?DateTimeImmutable $dateFrom = null,
        ?DateTimeImmutable $dateTo = null
    ): TeamFormAnalysis {
        $this->logger->info('Analyzing team form from database', [
            'team_id' => $teamId->getValue(),
            'date_from' => $dateFrom?->format('Y-m-d H:i:s'),
            'date_to' => $dateTo?->format('Y-m-d H:i:s'),
        ]);

        $teamMatches = $this->teamMatchRepository->findByTeamId($teamId, $dateFrom, $dateTo);
        $totalMatches = count($teamMatches);

        if ($totalMatches < self::MINIMUM_MATCHES) {
            throw InsufficientMatchesException::forTeam($teamId, $totalMatches, self::MINIMUM_MATCHES);
        }

        $wins = 0;
        $matchIds = [];
        $regionCounts = [];

        foreach ($teamMatches as $teamMatch) {
            $matchIds[] = $teamMatch->getMatchId();

            if ($teamMatch->isWin()) {
                $wins++;
            }

            $matchEntity = $this->matchRepository->findByMatchId($teamMatch->getMatchId());

            if ($matchEntity !== null && $matchEntity->getRegion() !== null) {
                $region = $matchEntity->getRegion();
                $regionId = $region->getId();
                $regionCounts[$regionId] = ($regionCounts[$regionId] ?? 0) + 1;
            } else {
                $this->logger->warning('Match not found or has no region', [
                    'match_id' => $teamMatch->getMatchId()->getValue(),
                    'team_id' => $teamId->getValue(),
                ]);
            }
        }

        if (empty($teamMatches)) {
            throw InsufficientMatchesException::forTeam($teamId, 0, self::MINIMUM_MATCHES);
        }

        $lastMatchEntity = $this->matchRepository->findByMatchId($teamMatches[0]->getMatchId());
        $lastMatchTime = $lastMatchEntity?->getStartTime() ?? new DateTimeImmutable();

        $mostCommonRegion = $this->getMostCommonRegion($regionCounts);
        $winrate = Winrate::fromMatchResults($wins, $totalMatches);
        $form = TeamForm::fromWinrate($winrate->getValue());

        $this->logger->info('Team analysis completed (from database)', [
            'team_id' => $teamId->getValue(),
            'matches' => $totalMatches,
            'wins' => $wins,
            'winrate' => $winrate->getValue(),
            'form' => $form->value,
            'region' => $mostCommonRegion->getName(),
        ]);

        return new TeamFormAnalysis(
            teamId: $teamId,
            teamName: new TeamName(sprintf('Team %d', $teamId->getValue())),
            form: $form,
            winrate: $winrate,
            region: $mostCommonRegion,
            matchesAnalyzed: $matchIds,
            lastMatchTime: $lastMatchTime,
            analyzedAt: new DateTimeImmutable(),
        );
    }

    private function getMostCommonRegion(array $regionCounts): Region
    {
        if (empty($regionCounts)) {
            return Region::unknown();
        }

        arsort($regionCounts);
        $mostCommonRegionId = array_key_first($regionCounts);

        return Region::fromId($mostCommonRegionId);
    }
}
