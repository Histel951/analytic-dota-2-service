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
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'fantasy:export-top-metrics',
    description: 'Формирует JSON как /api/fantasy/top-metrics и сохраняет локально в файл'
)]
final class ExportTopFantasyMetricsCommand extends Command
{
    private const DEFAULT_OUTPUT_DIR = 'var/exports/fantasy';

    public function __construct(
        private readonly GetTopFantasyMetricsUseCase $useCase,
        private readonly ValidatorInterface $validator,
        private readonly string $projectDir,
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
                'ID команд через запятую (например: 2586976,2163,39)',
            )
            ->addOption(
                'player-ids',
                'p',
                InputOption::VALUE_REQUIRED,
                'ID игроков через запятую (матчи с заменами исключаются)',
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
                'Начало периода (Y-m-d или Y-m-d H:i:s)',
            )
            ->addOption(
                'date-to',
                null,
                InputOption::VALUE_REQUIRED,
                'Конец периода (Y-m-d или Y-m-d H:i:s)',
            )
            ->addOption(
                'matches-limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Лимит последних матчей на каждую команду',
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Путь к JSON-файлу (относительно корня проекта или абсолютный)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $teamIds = $this->parseIdsOption($input->getOption('team-ids'));
        if ($teamIds === false) {
            $io->error('Некорректный формат --team-ids. Пример: --team-ids="2586976,2163"');
            return Command::FAILURE;
        }

        $playerIds = $this->parseIdsOption($input->getOption('player-ids'));
        if ($playerIds === false) {
            $io->error('Некорректный формат --player-ids. Пример: --player-ids="94155156,86725175"');
            return Command::FAILURE;
        }

        $dateFrom = $this->parseDateOption($input->getOption('date-from'));
        $dateTo = $this->parseDateOption($input->getOption('date-to'));

        if ($dateFrom === false || $dateTo === false) {
            $io->error('Некорректный формат даты. Используйте Y-m-d или Y-m-d H:i:s');
            return Command::FAILURE;
        }

        $matchesLimitOption = $input->getOption('matches-limit');
        $matchesLimit = $matchesLimitOption !== null ? (int) $matchesLimitOption : null;

        $query = new GetTopFantasyMetricsQuery(
            teamIds: $teamIds,
            playerIds: $playerIds,
            minMatchesPerPlayer: (int) $input->getOption('min-matches'),
            dateFrom: $dateFrom?->format('Y-m-d H:i:s'),
            dateTo: $dateTo?->format('Y-m-d H:i:s'),
            matchesLimit: $matchesLimit,
        );

        $violations = $this->validator->validate($query);
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error(sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage()));
            }
            return Command::FAILURE;
        }

        $outputPath = $this->resolveOutputPath($input->getOption('output'), $query);

        $io->title('Экспорт top-metrics в JSON');
        $io->writeln(sprintf('Файл: <info>%s</info>', $outputPath));
        $io->writeln('');

        try {
            $result = $this->useCase->execute($query);

            $directory = dirname($outputPath);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                $io->error(sprintf('Не удалось создать директорию: %s', $directory));
                return Command::FAILURE;
            }

            $json = json_encode(
                $result,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            if (file_put_contents($outputPath, $json . PHP_EOL) === false) {
                $io->error(sprintf('Не удалось записать файл: %s', $outputPath));
                return Command::FAILURE;
            }

            $io->success(sprintf(
                'JSON сохранён (%d role groups, %d байт)',
                count($result['role_groups'] ?? []),
                strlen($json)
            ));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error([
                'Ошибка при экспорте top-metrics',
                $e->getMessage(),
            ]);

            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function resolveOutputPath(?string $outputOption, GetTopFantasyMetricsQuery $query): string
    {
        if ($outputOption !== null && $outputOption !== '') {
            if ($this->isAbsolutePath($outputOption)) {
                return $outputOption;
            }

            return $this->projectDir . DIRECTORY_SEPARATOR . ltrim(
                str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $outputOption),
                DIRECTORY_SEPARATOR
            );
        }

        return $this->projectDir
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, self::DEFAULT_OUTPUT_DIR)
            . DIRECTORY_SEPARATOR
            . $this->buildDefaultFilename($query);
    }

    private function buildDefaultFilename(GetTopFantasyMetricsQuery $query): string
    {
        $parts = ['top-metrics'];

        if ($query->teamIds !== null) {
            $parts[] = $this->formatIdsPart('teams', $query->teamIds);
        }

        if ($query->playerIds !== null) {
            $parts[] = $this->formatIdsPart('players', $query->playerIds);
        }

        $parts[] = 'min-' . $query->minMatchesPerPlayer;

        if ($query->dateFrom !== null) {
            $parts[] = 'from-' . $this->sanitizeForFilename($query->dateFrom);
        }

        if ($query->dateTo !== null) {
            $parts[] = 'to-' . $this->sanitizeForFilename($query->dateTo);
        }

        if ($query->matchesLimit !== null) {
            $parts[] = 'limit-' . $query->matchesLimit;
        }

        $parts[] = (new DateTimeImmutable())->format('Ymd-His');

        return implode('_', $parts) . '.json';
    }

    /**
     * @param int[] $ids
     */
    private function formatIdsPart(string $label, array $ids): string
    {
        $joined = implode('-', $ids);

        // Длинные списки ID сокращаем: count + короткий hash
        if (strlen($joined) > 80) {
            return sprintf('%s-%d-%s', $label, count($ids), substr(sha1($joined), 0, 8));
        }

        return $label . '-' . $joined;
    }

    private function sanitizeForFilename(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_-]+/', '-', $value) ?? $value;
    }

    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }

    /**
     * @return int[]|null|false null = опция не передана, false = невалидный формат
     */
    private function parseIdsOption(?string $idsString): array|null|false
    {
        if ($idsString === null) {
            return null;
        }

        $ids = $this->parseIds($idsString);

        return $ids === [] ? false : $ids;
    }

    /**
     * @return int[]
     */
    private function parseIds(string $idsString): array
    {
        $ids = [];

        foreach (explode(',', $idsString) as $part) {
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

        foreach (['Y-m-d H:i:s', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $format === 'Y-m-d' ? $date->setTime(0, 0, 0) : $date;
            }
        }

        return false;
    }
}
