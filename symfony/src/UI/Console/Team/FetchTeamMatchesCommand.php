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
    name: 'team:fetch-matches',
    description: 'Получает последние матчи профессиональной команды и отправляет их на обработку'
)]
final class FetchTeamMatchesCommand extends Command
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
                'team-id',
                InputArgument::REQUIRED,
                'ID профессиональной команды в Dota 2'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Максимальное количество матчей для обработки',
            )
            ->addOption(
                'date-from',
                null,
                InputOption::VALUE_REQUIRED,
                'Начало периода (формат: Y-m-d или Y-m-d H:i:s)',
            )
            ->addOption(
                'date-to',
                null,
                InputOption::VALUE_REQUIRED,
                'Конец периода (формат: Y-m-d или Y-m-d H:i:s)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $teamId = (int) $input->getArgument('team-id');
        $limit = $input->getOption('limit') !== null 
            ? (int) $input->getOption('limit') 
            : null;

        $dateFrom = $this->parseDateOption($input->getOption('date-from'));
        $dateTo = $this->parseDateOption($input->getOption('date-to'));

        if ($dateFrom === false || $dateTo === false) {
            $io->error('Некорректный формат даты. Используйте формат Y-m-d или Y-m-d H:i:s');
            return Command::FAILURE;
        }

        $request = new FetchTeamMatchesRequest(
            teamId: $teamId,
            limit: $limit,
            dateFrom: $dateFrom,
            dateTo: $dateTo
        );

        $violations = $this->validator->validate($request);

        if (count($violations) > 0) {
            $io->error('Некорректные параметры команды:');
            foreach ($violations as $violation) {
                $io->writeln(sprintf('  - %s', $violation->getMessage()));
            }
            return Command::FAILURE;
        }

        $io->title('Обработка матчей команды');
        $io->info(sprintf('Получение матчей для команды %d', $teamId));

        if ($limit !== null) {
            $io->info(sprintf('Лимит: %d матчей', $limit));
        }

        if ($dateFrom !== null) {
            $io->info(sprintf('Начало периода: %s', $dateFrom->format('Y-m-d H:i:s')));
        }

        if ($dateTo !== null) {
            $io->info(sprintf('Конец периода: %s', $dateTo->format('Y-m-d H:i:s')));
        }

        try {
            $matchesCount = $this->fetchTeamMatchesUseCase->execute(
                $teamId,
                $limit,
                $dateFrom,
                $dateTo
            );

            if ($matchesCount === 0) {
                $io->warning('Не найдено матчей, соответствующих указанным критериям');
                $io->note('Если API вернул матчи, но все были пропущены - проверьте логи приложения');
                return Command::SUCCESS;
            }

            $io->success(sprintf(
                'Успешно отправлено %d матчей на обработку',
                $matchesCount
            ));

            $io->note([
                'Матчи отправлены в очередь Kafka и будут обработаны асинхронно.',
                '',
                'Если некоторые матчи были пропущены (известная проблема в библиотеке histel951/dota-2-api),',
                'они будут указаны в логах с уровнем WARNING.',
                '',
                'Проверить статус обработки можно через:',
                '  docker-compose logs -f worker_kafka worker_async',
                '',
                'или в базе данных:',
                '  docker exec -it dota_postgres psql -U dota -d dota -c "SELECT * FROM match_processing ORDER BY parsed_at DESC LIMIT 10;"'
            ]);

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $io->error([
                'Ошибка при обработке матчей команды',
                $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Парсит опцию даты из строки
     *
     * @param string|null $dateString
     * @return \DateTimeImmutable|null|false null если дата не указана, false если формат некорректен
     */
    private function parseDateOption(?string $dateString): \DateTimeImmutable|null|false
    {
        if ($dateString === null) {
            return null;
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d'];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $dateString);
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
