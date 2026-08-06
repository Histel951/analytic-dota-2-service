<?php
declare(strict_types=1);

namespace App\UI\Console\Team;

use App\Application\Team\Dto\FetchTeamMatchesRequest;
use App\Application\Team\UseCase\FetchTeamMatchesUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'team:fetch-multiple-matches',
    description: 'Получает последние матчи нескольких профессиональных команд и отправляет их на обработку'
)]
final class FetchMultipleTeamsMatchesCommand extends Command
{
    public function __construct(
        private readonly FetchTeamMatchesUseCase $fetchTeamMatchesUseCase,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'team-ids',
                InputArgument::REQUIRED,
                'ID команд через запятую (например: 2586976,2163,39,15)'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Максимальное количество матчей для каждой команды',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $teamIdsString = $input->getArgument('team-ids');
        $teamIds = $this->parseTeamIds($teamIdsString);

        if (empty($teamIds)) {
            $io->error('Не указаны ID команд или указан некорректный формат');
            $io->note('Используйте формат: team:fetch-multiple-matches "2586976,2163,39"');
            return Command::FAILURE;
        }

        $limit = $input->getOption('limit') !== null 
            ? (int) $input->getOption('limit') 
            : null;

        $io->title('Обработка матчей нескольких команд');
        $io->info(sprintf('Всего команд для обработки: %d', count($teamIds)));
        
        if ($limit !== null) {
            $io->info(sprintf('Лимит матчей на команду: %d', $limit));
        }

        $io->writeln('');
        $io->writeln('Команды для обработки:');
        $io->listing(array_map(fn($id) => "Team ID: $id", $teamIds));
        $io->writeln('');

        $totalMatches = 0;
        $successCount = 0;
        $failedTeams = [];

        foreach ($teamIds as $index => $teamId) {
            $io->section(sprintf('[%d/%d] Обработка команды %d', $index + 1, count($teamIds), $teamId));

            try {
                $request = new FetchTeamMatchesRequest(
                    teamId: $teamId,
                    limit: $limit,
                    dateFrom: null,
                    dateTo: null
                );

                $violations = $this->validator->validate($request);

                if (count($violations) > 0) {
                    $io->warning(sprintf('Пропускаем команду %d - некорректные параметры', $teamId));
                    foreach ($violations as $violation) {
                        $io->writeln(sprintf('  - %s', $violation->getMessage()));
                    }
                    $failedTeams[$teamId] = 'Validation failed';
                    continue;
                }

                $matchesCount = $this->fetchTeamMatchesUseCase->execute(
                    $teamId,
                    $limit,
                    null,
                    null
                );

                if ($matchesCount === 0) {
                    $io->warning(sprintf('Команда %d: не найдено матчей', $teamId));
                } else {
                    $io->success(sprintf('Команда %d: отправлено %d матчей', $teamId, $matchesCount));
                    $totalMatches += $matchesCount;
                    $successCount++;
                }

            } catch (\Throwable $e) {
                $io->error([
                    sprintf('Команда %d: ошибка при обработке', $teamId),
                    $e->getMessage(),
                ]);
                $failedTeams[$teamId] = $e->getMessage();
            }

            $io->writeln('');
        }

        // Итоговая статистика
        $io->newLine(2);
        $io->title('Итоги обработки');

        $io->table(
            ['Метрика', 'Значение'],
            [
                ['Всего команд', count($teamIds)],
                ['Успешно обработано', $successCount],
                ['С ошибками', count($failedTeams)],
                ['Всего матчей отправлено', $totalMatches],
            ]
        );

        if (!empty($failedTeams)) {
            $io->warning('Команды с ошибками:');
            $errorTable = [];
            foreach ($failedTeams as $teamId => $error) {
                $errorTable[] = [$teamId, mb_substr($error, 0, 100)];
            }
            $io->table(['Team ID', 'Ошибка'], $errorTable);
        }

        if ($totalMatches > 0) {
            $io->note([
                'Матчи отправлены в очередь Kafka и будут обработаны асинхронно.',
                '',
                'Проверить статус обработки:',
                '  docker-compose logs -f worker_kafka worker_async',
                '',
                'Или в базе данных:',
                '  docker exec -it dota_postgres psql -U dota -d dota -c "SELECT status, COUNT(*) FROM match_processing GROUP BY status;"'
            ]);
        }

        return count($failedTeams) === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return int[]
     */
    private function parseTeamIds(string $teamIdsString): array
    {
        $parts = explode(',', $teamIdsString);
        $teamIds = [];

        foreach ($parts as $part) {
            $trimmed = trim($part);
            if (is_numeric($trimmed)) {
                $teamIds[] = (int) $trimmed;
            }
        }

        return $teamIds;
    }
}
