<?php
declare(strict_types=1);

namespace App\UI\Console\Match;

use App\Application\Match\UseCase\RetryFailedMatchesUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'match:retry-failed',
    description: 'Перезапускает обработку матчей со статусом "failed"'
)]
final class RetryFailedMatchesCommand extends Command
{
    public function __construct(
        private readonly RetryFailedMatchesUseCase $retryFailedMatchesUseCase,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Перезапуск обработки failed матчей');
        $io->info('Поиск матчей со статусом "failed"...');

        try {
            $result = $this->retryFailedMatchesUseCase->execute();

            if ($result['total'] === 0) {
                $io->success('Не найдено матчей со статусом "failed"');
                $io->note('Все матчи обработаны успешно!');
                return Command::SUCCESS;
            }

            $io->success(sprintf(
                'Найдено %d матчей со статусом "failed"',
                $result['total']
            ));

            $io->writeln('');
            $io->writeln('Матчи отправлены на повторную обработку:');
            
            // Показываем первые 20 match_ids
            $displayIds = array_slice($result['match_ids'], 0, 20);
            $io->listing($displayIds);

            if (count($result['match_ids']) > 20) {
                $io->note(sprintf(
                    '... и ещё %d матчей',
                    count($result['match_ids']) - 20
                ));
            }

            $io->writeln('');
            $io->note([
                'Матчи отправлены в очередь Kafka и будут обработаны асинхронно.',
                '',
                'Проверить статус обработки:',
                '  docker-compose logs -f worker_kafka worker_async',
                '',
                'Статистика по статусам:',
                '  docker exec -it dota_postgres psql -U dota -d dota -c "SELECT status, COUNT(*) FROM match_processing GROUP BY status;"',
                '',
                'Просмотр failed матчей:',
                '  docker exec -it dota_postgres psql -U dota -d dota -c "SELECT match_id, parsed_at FROM match_processing WHERE status = \'failed\' ORDER BY parsed_at DESC LIMIT 10;"'
            ]);

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $io->error([
                'Ошибка при перезапуске обработки',
                $e->getMessage(),
            ]);

            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
