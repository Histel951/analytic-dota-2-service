# Соглашения

## Стиль

- `declare(strict_types=1)` (пропущен в `MatchProcessingStatus`, интерфейсах репозиториев `Domain/Match/Repository` кроме `FantasyStatsRepository`, `PsqlMatchRepository`, `KafkaTopicsConfig`, `ParseMatchKafkaCommand`), классы `final`, где можно — `final readonly`, constructor property promotion, именованные аргументы при создании сущностей и DTO
- Типизированные константы (`private const array`, `private const int`) в новом коде
- Идентификаторы и статистика — VO бандла `histel951/dota-2-api` (`MatchId`, `PlayerId`, `TeamId`, `Region`, `GPM`, …), а не `int`. Внутри модуля Fantasy значения из ClickHouse — скаляры (`PlayerMatchStatistic`, `PlayerAnalyzedMatch`)
- Комментарии и тексты консоли — на русском, сообщения логов и исключений — на английском
- Логи — `LoggerInterface` с массивом контекста (`match_id`, `team_id`, `error`); в хэндлерах на каждый шаг пишется `info`, на ошибку — `error`
- Ошибки валидации — `Symfony\Validator` атрибуты на DTO запроса (`#[Assert\Positive]`, `#[Assert\Choice]`, `#[Assert\All]`) + бизнес-проверки в Service с именованными конструкторами исключений (`InvalidPaginationException::invalidLimit()`, `InsufficientMatchesException::forTeam()`)
- Ответ HTTP — отдельный класс `…Response extends JsonResponse` в `Infrastructure/Http/Response/`; DTO результата сам отдаёт `toArray()` с ключами в `snake_case`
- SQL — строками в репозитории, Postgres через `?` + типы DBAL, ClickHouse через `:name` (`smi2` подставляет на клиенте). Списки id в ClickHouse вклеиваются `implode` из `getValue()` VO (целые). Новый код должен делать так же или биндинг, но никогда не вставлять сырой пользовательский ввод

## Раскладка модуля

```
Domain/<M>/{Entity, ValueObject(s), Enum, Repository (интерфейсы), Service, Dto}
Application/<M>/{Dto (запрос с Assert + результат с toArray), UseCase, Service, Exception, Handler, Message}
Infrastructure/{Postgres,ClickHouse}/Repository/<Impl>Repository
UI/Http/Controller/<M>Controller, UI/Console/<M>/<Name>Command
```

Новый ClickHouse-репозиторий: реализация + алиас интерфейса и `$clickhouse: '@ClickHouseDB\Client'` в `services.yaml`. Postgres-репозиторий с единственной реализацией автовайрится сам.

## Рецепт: новая метрика игрока в конвейере

1. Поле в бандле `dota-2-api` (VO + экстрактор + маппер), обновить `composer.lock` (поправив код под изменения бандла)
2. `migrations/clickhouse/NNN_add_<field>_to_player_match_stats.sql` — `ADD COLUMN IF NOT EXISTS … DEFAULT 0`
3. `PlayerMatchStats` (поле + геттер) → `PlayerMatchExtractor` → `AnalyticMatchAnalyticsRepository` (`COLUMNS`, `createStatsData`, `mapRowToPlayerMatchStats` с `?? 0` для старых строк)
4. Если метрика даёт fantasy-очки: параметр в `services.yaml` → `FantasyScoringConfig` → `FantasyScoreCalculator` → `FantasyScoreResult` → миграция `player_match_fantasy_stats` → `ClickHouseFantasyStatsRepository` (`COLUMNS`, `mapToRow`) → `ClickHouseFantasyMetricAnalysisRepository::FANTASY_METRICS` → `EmblemType::getMetrics()` (иначе `fromMetric` бросит исключение)
5. Для топа игроков: whitelist в `GetTopPlayersQuery` **и** `GetTopPlayersByMetricService::SUPPORTED_METRICS`

Так делались `camps_stacked` (`ad63924`) и `tormentor/smoke/watcher` (`c958160`).

## Рецепт: новый HTTP-эндпоинт

DTO запроса с `#[Assert]` в `Application/<M>/Dto` → UseCase (примитивы → VO) → Service → интерфейс репозитория в Domain → реализация в Infrastructure (+ алиас) → `…Response` в `Infrastructure/Http/Response` → контроллер в `UI/Http/Controller` с `#[Route('/api/…')]`: собрать DTO, `$validator->validate()`, при нарушениях `throw new ValidationFailedException`. Новые доменные исключения, которые должны давать 400, добавить в `match` в `ExceptionSubscriber`.

## Рецепт: новая миграция

Следующий номер в `migrations/{psql,clickhouse}/`, идемпотентно (`IF NOT EXISTS`), и строка в таблице database.md → «Миграции».

## Проверка

Тестов нет. Минимум перед коммитом:

```bash
cd symfony
php bin/console lint:container
php bin/console debug:router | grep api
php bin/console list fantasy   # и прочие пространства команд
```

Конвейер вживую: `parse:kafka:match <id>` → `docker logs -f dota_worker_kafka dota_worker_async` → статус в `match_processing`.

## Git

Ветки `feature/<name>` → PR в `main`. Сообщения коммитов короткие, в нижнем регистре, на английском или русском (`top fantasy endpoint + retry failed`).
