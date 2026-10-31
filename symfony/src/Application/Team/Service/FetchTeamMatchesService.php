<?php
declare(strict_types=1);

namespace App\Application\Team\Service;

use App\Application\Match\Message\ParseMatchMessage;
use App\Application\Team\Exception\InvalidDateRangeException;
use App\Domain\Team\Entity\TeamMatch;
use App\Domain\Team\Repository\TeamMatchRepository;
use App\Domain\Team\Source\TeamMatchesSource;
use App\Shared\Domain\TeamId;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class FetchTeamMatchesService
{
    public function __construct(
        private TeamMatchesSource $teamMatchesSource,
        private MessageBusInterface $messageBus,
        private TeamMatchRepository $teamMatchRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * Получает матчи команды и отправляет их на обработку
     *
     * @return int Количество матчей, отправленных на обработку
     */
    public function fetchAndDispatchMatches(
        int $teamId,
        ?int $limit = null,
        ?DateTimeImmutable $dateFrom = null,
        ?DateTimeImmutable $dateTo = null
    ): int {
        $this->validateDateRange($dateFrom, $dateTo);

        $this->logger->info('Fetching matches for team', [
            'team_id' => $teamId,
            'limit' => $limit,
            'date_from' => $dateFrom?->format('Y-m-d H:i:s'),
            'date_to' => $dateTo?->format('Y-m-d H:i:s'),
        ]);

        try {
            $teamMatches = $this->teamMatchesSource->listByTeam(new TeamId($teamId));

            $processedCount = 0;
            $teamMatchEntities = [];

            foreach ($teamMatches as $match) {
                if ($limit !== null && $processedCount >= $limit) {
                    break;
                }

                if (!$this->isMatchInDateRange($match->getStartTime(), $dateFrom, $dateTo)) {
                    continue;
                }

                $matchId = $match->getMatchId()->getValue();

                $this->messageBus->dispatch(new ParseMatchMessage($matchId));

                $teamMatchEntity = new TeamMatch(
                    teamId: new TeamId($teamId),
                    matchId: $match->getMatchId(),
                    isWin: $match->isWon(),
                );
                $teamMatchEntities[] = $teamMatchEntity;

                $this->logger->debug('Match dispatched for processing', [
                    'match_id' => $matchId,
                    'team_id' => $teamId,
                    'match_start_time' => $match->getStartTime()->format('Y-m-d H:i:s'),
                    'is_win' => $match->isWon(),
                ]);

                $processedCount++;
            }

            if (!empty($teamMatchEntities)) {
                $this->teamMatchRepository->saveBulk($teamMatchEntities);
                
                $this->logger->info('Team-match relations saved', [
                    'team_id' => $teamId,
                    'relations_count' => count($teamMatchEntities),
                ]);
            }

            $this->logger->info('Team matches dispatched successfully', [
                'team_id' => $teamId,
                'matches_count' => $processedCount,
            ]);

            return $processedCount;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch team matches', [
                'team_id' => $teamId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function validateDateRange(?DateTimeImmutable $dateFrom, ?DateTimeImmutable $dateTo): void
    {
        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            throw InvalidDateRangeException::dateFromGreaterThanDateTo($dateFrom, $dateTo);
        }
    }

    private function isMatchInDateRange(
        DateTimeImmutable $matchStartTime,
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo
    ): bool {
        if ($dateFrom !== null && $matchStartTime < $dateFrom) {
            return false;
        }

        if ($dateTo !== null && $matchStartTime > $dateTo) {
            return false;
        }

        return true;
    }
}
