<?php
declare(strict_types=1);

namespace App\Application\Team\UseCase;

use App\Application\Team\Dto\TeamFormResult;
use App\Application\Team\Service\TeamFormAnalyzer;
use App\Domain\Team\Repository\TeamFormAnalysisRepository;
use DateTimeImmutable;
use App\Shared\Domain\TeamId;
use Psr\Log\LoggerInterface;

final readonly class AnalyzeTeamsFormUseCase
{
    public function __construct(
        private TeamFormAnalyzer $teamFormAnalyzer,
        private TeamFormAnalysisRepository $repository,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param int[] $teamIds
     */
    public function execute(
        array $teamIds,
        ?DateTimeImmutable $dateFrom = null,
        ?DateTimeImmutable $dateTo = null
    ): TeamFormResult {
        $this->logger->info('Starting team form analysis', [
            'teams_count' => count($teamIds),
            'date_from' => $dateFrom?->format('Y-m-d H:i:s'),
            'date_to' => $dateTo?->format('Y-m-d H:i:s'),
        ]);

        $analyzedCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($teamIds as $teamIdValue) {
            try {
                $teamId = new TeamId($teamIdValue);

                $this->logger->info('Analyzing team', ['team_id' => $teamIdValue]);

                $analysis = $this->teamFormAnalyzer->analyze(
                    $teamId,
                    $dateFrom,
                    $dateTo
                );

                $this->repository->save($analysis);

                $analyzedCount++;

                $this->logger->info('Team analysis saved', [
                    'team_id' => $teamIdValue,
                    'form' => $analysis->getForm()->value,
                    'winrate' => $analysis->getWinrate()->getValue(),
                ]);

            } catch (\Throwable $e) {
                $failedCount++;
                $errors[$teamIdValue] = $e->getMessage();

                $this->logger->error('Failed to analyze team', [
                    'team_id' => $teamIdValue,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->logger->info('Team form analysis completed', [
            'analyzed' => $analyzedCount,
            'failed' => $failedCount,
        ]);

        return new TeamFormResult(
            analyzedTeams: $analyzedCount,
            failedTeams: $failedCount,
            errors: $errors,
        );
    }
}
