<?php
declare(strict_types=1);

namespace App\Statistics\UI\Console;

use App\Statistics\Application\UseCase\AnalyzeTeamsFormUseCase;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'team:analyze-form',
    description: 'Анализирует форму команд на основе последних матчей для определения фаворитов и аутсайдеров'
)]
final class AnalyzeTeamsFormCommand extends Command
{
    public function __construct(
        private readonly AnalyzeTeamsFormUseCase $analyzeTeamsFormUseCase,
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
                'date-from',
                null,
                InputOption::VALUE_REQUIRED,
                'Начало периода анализа (формат: Y-m-d или Y-m-d H:i:s)',
            )
            ->addOption(
                'date-to',
                null,
                InputOption::VALUE_REQUIRED,
                'Конец периода анализа (формат: Y-m-d или Y-m-d H:i:s)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $teamIdsString = $input->getArgument('team-ids');
        $teamIds = $this->parseTeamIds($teamIdsString);

        if (empty($teamIds)) {
            $io->error('Не указаны ID команд или указан некорректный формат');
            $io->note('Используйте формат: team:analyze-form "2586976,2163,39"');
            return Command::FAILURE;
        }

        $dateFrom = $this->parseDateOption($input->getOption('date-from'));
        $dateTo = $this->parseDateOption($input->getOption('date-to'));

        if ($dateFrom === false || $dateTo === false) {
            $io->error('Некорректный формат даты. Используйте формат Y-m-d или Y-m-d H:i:s');
            return Command::FAILURE;
        }

        $io->title('Анализ формы команд');
        $io->info(sprintf('Анализ %d команд', count($teamIds)));

        if ($dateFrom !== null) {
            $io->info(sprintf('Начало периода: %s', $dateFrom->format('Y-m-d H:i:s')));
        }

        if ($dateTo !== null) {
            $io->info(sprintf('Конец периода: %s', $dateTo->format('Y-m-d H:i:s')));
        }

        $io->writeln('');
        $io->writeln('Команды для анализа:');
        $io->listing(array_map(fn($id) => "Team ID: $id", $teamIds));
        $io->writeln('');

        try {
            $result = $this->analyzeTeamsFormUseCase->execute(
                $teamIds,
                $dateFrom,
                $dateTo
            );

            $io->success(sprintf(
                'Анализ завершен: успешно - %d, ошибок - %d',
                $result->analyzedTeams,
                $result->failedTeams
            ));

            if ($result->failedTeams > 0) {
                $io->warning('Не удалось проанализировать следующие команды:');
                $errorTable = [];
                foreach ($result->errors as $teamId => $error) {
                    $errorTable[] = [$teamId, $error];
                }
                $io->table(['Team ID', 'Ошибка'], $errorTable);
            }

            if ($result->analyzedTeams > 0) {
                $io->note([
                    'Результаты анализа сохранены в ClickHouse.',
                    '',
                    'Просмотр фаворитов:',
                    '  SELECT team_name, form, winrate, region FROM team_form_analysis',
                    '  WHERE form IN (\'prime\', \'high\') ORDER BY winrate DESC LIMIT 10;',
                    '',
                    'Просмотр аутсайдеров:',
                    '  SELECT team_name, form, winrate, region FROM team_form_analysis',
                    '  WHERE form IN (\'low\', \'medium\') ORDER BY winrate ASC LIMIT 10;',
                ]);
            }

            return $result->failedTeams === 0 ? Command::SUCCESS : Command::FAILURE;

        } catch (\Throwable $e) {
            $io->error([
                'Ошибка при анализе команд',
                $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
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

    private function parseDateOption(?string $dateString): DateTimeImmutable|null|false
    {
        if ($dateString === null) {
            return null;
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d'];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $dateString);
            if ($date !== false) {
                if ($format === 'Y-m-d') {
                    $date = $date->setTime(0, 0, 0);
                }
                return $date;
            }
        }

        return false;
    }
}
