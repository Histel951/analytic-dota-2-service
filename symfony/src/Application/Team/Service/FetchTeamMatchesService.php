<?php
declare(strict_types=1);

namespace App\Application\Team\Service;

use App\Application\Match\Message\ParseMatchMessage;
use App\Application\Team\Exception\InvalidDateRangeException;
use App\Domain\Team\Entity\TeamMatch;
use App\Domain\Team\Repository\TeamMatchRepository;
use DateTimeImmutable;
use Histel951\Dota2Api\Client\Dota2ApiClient;
use Histel951\Dota2Api\Domain\Common\ValueObjects\TeamId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class FetchTeamMatchesService
{
    public function __construct(
        private Dota2ApiClient $dota2ApiClient,
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
            $teamMatches = $this->dota2ApiClient
                ->teams()
                ->getMatchesByTeam(new TeamId($teamId));

            $processedCount = 0;
            $skippedCount = 0;
            $teamMatchEntities = [];

            foreach ($teamMatches as $index => $match) {
                try {
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
                        isWin: $match->matchOutcome()->isWon(),
                    );
                    $teamMatchEntities[] = $teamMatchEntity;
                    
                    $this->logger->debug('Match dispatched for processing', [
                        'match_id' => $matchId,
                        'team_id' => $teamId,
                        'match_start_time' => $match->getStartTime()->format('Y-m-d H:i:s'),
                        'is_win' => $match->matchOutcome()->isWon(),
                    ]);

                    $processedCount++;

                } catch (\TypeError $e) {
                    $skippedCount++;
                    
                    $this->logger->warning('Skipped match due to API library error', [
                        'team_id' => $teamId,
                        'match_index' => $index,
                        'error' => $e->getMessage(),
                        'note' => 'This is a known issue in histel951/dota-2-api library mapper',
                    ]);
                    
                    continue;
                }
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
                'skipped_count' => $skippedCount,
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
