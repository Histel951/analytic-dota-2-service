<?php
declare(strict_types=1);

namespace App\Fantasy\UI\Console;

use App\Fantasy\Application\Dto\GetTopFantasyMetricsQuery;
use App\Fantasy\Application\UseCase\GetTopFantasyMetricsUseCase;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'fantasy:top-metrics',
    description: 'Получает топ-3 метрики для каждой группы ролей, дающие наибольшее количество фентези очков'
)]
final class GetTopFantasyMetricsCommand extends Command
{
    public function __construct(
        private readonly GetTopFantasyMetricsUseCase $useCase,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'team-ids',
                't',
                InputOption::VALUE_REQUIRED,
                'ID команд через запятую для анализа (например: 2586976,2163,39). Если не указан - по всем командам',
            )
            ->addOption(
                'player-ids',
                'p',
                InputOption::VALUE_REQUIRED,
                'ID игроков через запятую для анализа (например: 94155156,86725175). Матчи с заменами будут исключены',
            )
            ->addOption(
                'min-matches',
                'm',
                InputOption::VALUE_REQUIRED,
                'Минимальное количество матчей на игрока',
                '10'
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
            )
            ->addOption(
                'matches-limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Лимит матчей для анализа',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $teamIdsOption = $input->getOption('team-ids');
        $teamIds = null;
        
        if ($teamIdsOption !== null) {
            $teamIds = $this->parseIds($teamIdsOption);
            if (empty($teamIds)) {
                $io->error('Не указаны ID команд или указан некорректный формат');
                $io->note('Используйте формат: --team-ids="2586976,2163,39"');
                return Command::FAILURE;
            }
        }

        $playerIdsOption = $input->getOption('player-ids');
        $playerIds = null;
        
        if ($playerIdsOption !== null) {
            $playerIds = $this->parseIds($playerIdsOption);
            if (empty($playerIds)) {
                $io->error('Не указаны ID игроков или указан некорректный формат');
                $io->note('Используйте формат: --player-ids="94155156,86725175"');
                return Command::FAILURE;
            }
        }
        
        $minMatches = (int) $input->getOption('min-matches');
        
        $matchesLimitOption = $input->getOption('matches-limit');
        $matchesLimit = $matchesLimitOption !== null ? (int) $matchesLimitOption : null;

        $dateFrom = $this->parseDateOption($input->getOption('date-from'));
        $dateTo = $this->parseDateOption($input->getOption('date-to'));

        if ($dateFrom === false || $dateTo === false) {
            $io->error('Некорректный формат даты. Используйте формат Y-m-d или Y-m-d H:i:s');
            return Command::FAILURE;
        }

        $io->title('Анализ топ-метрик для фентези очков');
        
        if ($teamIds !== null) {
            $io->info(sprintf('Команды: %s', implode(', ', $teamIds)));
        } else {
            $io->info('Анализ по всем командам');
        }

        if ($playerIds !== null) {
            $io->info(sprintf('Игроки: %s (матчи с заменами будут исключены)', implode(', ', $playerIds)));
        }
        
        $io->info(sprintf('Минимальное количество матчей на игрока: %d', $minMatches));

        if ($dateFrom !== null) {
            $io->info(sprintf('Начало периода: %s', $dateFrom->format('Y-m-d H:i:s')));
        }

        if ($dateTo !== null) {
            $io->info(sprintf('Конец периода: %s', $dateTo->format('Y-m-d H:i:s')));
        }

        if ($matchesLimit !== null) {
            $io->info(sprintf('Лимит матчей: %d', $matchesLimit));
        }

        $io->writeln('');

        try {
            $query = new GetTopFantasyMetricsQuery(
                teamIds: $teamIds,
                playerIds: $playerIds,
                minMatchesPerPlayer: $minMatches,
                dateFrom: $dateFrom?->format('Y-m-d H:i:s'),
                dateTo: $dateTo?->format('Y-m-d H:i:s'),
                matchesLimit: $matchesLimit,
            );

            $result = $this->useCase->execute($query);

            $io->success('Анализ завершен успешно');
            $io->writeln('');

            foreach ($result['role_groups'] as $roleGroup) {
                $io->section(sprintf(
                    '%s (Роли: %s)',
                    $roleGroup['group_label'],
                    implode(', ', $roleGroup['roles'])
                ));

                $io->writeln(sprintf(
                    'Проанализировано: %d игроков, %d матчей',
                    $roleGroup['players_analyzed'],
                    $roleGroup['matches_analyzed']
                ));
                $io->writeln('');

                $io->writeln('Топ-3 метрики:');
                $tableData = [];
                foreach ($roleGroup['top_metrics'] as $index => $metric) {
                    $tableData[] = [
                        $index + 1,
                        $metric['metric'],
                        number_format($metric['avg_value'], 2),
                        number_format($metric['contribution_percent'], 2) . '%',
                    ];
                }
                $io->table(['#', 'Метрика', 'Среднее значение', 'Вклад в total_score'], $tableData);
                $io->writeln('');
            }

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $io->error([
                'Ошибка при анализе метрик',
                $e->getMessage(),
            ]);

            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * @return int[]
     */
    private function parseIds(string $idsString): array
    {
        $parts = explode(',', $idsString);
        $ids = [];

        foreach ($parts as $part) {
            $trimmed = trim($part);
            if (is_numeric($trimmed)) {
                $ids[] = (int) $trimmed;
            }
        }

        return $ids;
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
