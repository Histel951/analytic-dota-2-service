# Архитектура

Состояние после шага 2 рефакторинга (refactoring-plan.md): код разложен по bounded contexts, логика — как в MVP. Целевое устройство контекстов (порты чтения, конвейер на событиях, анализ в домене) — refactoring-plan.md, шаги 3–5.

## Контексты и слои (`symfony/src/`)

Раскладка «контекст → слой». Внутри слоя классы разложены по типу или по поддомену.

```
Kernel.php
Shared/
  Domain/                      — свои VO: MatchId, PlayerId, TeamId, PlayerRole (0..5), Side, Region/{Region, RegionType},
                                 Exception/* (Invalid*Exception, DomainException)
  Infrastructure/ClickHouse/   — ClickHouseFactory (из $_ENV CLICKHOUSE_*) → сервис ClickHouseDB\Client
  UI/Http/                     — EventSubscriber/ExceptionSubscriber, Response/ErrorResponse, SuccessResponse (не используется)
Ingestion/                     — загрузка матчей и матчей команд из внешних источников
  Domain/
    Match/                     — ImportedMatch, ImportedPlayer (модель импорта), MatchEntity (matches), MatchPlayers (match_players),
                                 PlayerMatchStats (строка player_match_stats, скаляры + Shared VO)
    Processing/                — MatchProcessing (match_processing, переходы markAs*), MatchProcessingStatus
    Team/                      — TeamMatch (team_matches), TeamMatchRef (матч из истории команды)
    Port/                      — MatchSource, TeamMatchesSource (+ *SourceException); MatchRepository, MatchPlayersRepository,
                                 MatchProcessingRepository, AnalyticMatchRepository (CH player_match_stats), TeamMatchRepository
  Application/
    Handler/                   — #[AsMessageHandler]: FetchMatchFromApiHandler, ProcessMatchAnalyticsHandler,
                                 PersistMatchDataHandler, MatchFailedHandler
    Message/                   — ParseMatchMessage, MatchFetchedMessage, MatchAnalyticsProcessedMessage,
                                 MatchFantasyCalculateMessage, MatchFailedMessage, MatchFailedKafkaMessage
    UseCase/                   — ParseMatchUseCase, RetryFailedMatchesUseCase, FetchTeamMatchesUseCase
    Service/                   — FetchTeamMatchesService
    Dto/, Exception/           — ParseMatchRequest, FetchTeamMatchesRequest; InvalidDateRange/InvalidLimit/InvalidTeamIdException
                                 (два последних не используются)
  Infrastructure/
    Source/OpenDota/           — OpenDotaMatchSource, OpenDotaTeamMatchesSource, OpenDotaMatchMapper (MatchDetail 2.0 → ImportedMatch).
                                 Единственное место, где используется бандл dota-api
    Persistence/Postgres/      — PsqlMatchRepository, PsqlMatchPlayerRepository, PsqlMatchProcessingRepository, PsqlTeamMatchRepository
    Persistence/ClickHouse/    — AnalyticMatchAnalyticsRepository (запись и чтение player_match_stats)
    Kafka/                     — MatchFailedKafkaSerializer (только encode → JSON); KafkaTopicsConfig, MatchProducer (не используются)
  UI/
    Http/                      — Controller/MatchController (POST /api/matches/parse), Response/MatchAcceptedResponse
    Console/                   — parse:kafka:match, match:retry-failed, team:fetch-matches, team:fetch-multiple-matches
Fantasy/                       — fantasy-очки и их анализ
  Domain/
    Scoring/                   — FantasyScoreCalculator, FantasyScoringConfig (коэффициенты из parameters), FantasyScoreResult
    Analysis/                  — RoleGroup (core_side [1,3], mid [2], support [4,5], квоты эмблем), EmblemType,
                                 SelectTopMetricsByEmblemRules, RoleGroupMetricRecommendation, TopComboRecommendation, PlayerInCombo,
                                 MetricContribution, PlayerMetrics, PlayerAnalyzedMatch, PlayerMatchStatistic
    Port/                      — FantasyStatsRepository (CH player_match_fantasy_stats), FantasyMetricAnalysisRepository
  Application/
    Handler/                   — ProcessFantasyScoreHandler (последний шаг конвейера импорта)
    UseCase/, Service/, Dto/   — GetTopFantasyMetricsUseCase, GetTopFantasyMetricsService (домен → DTO), Dto/*
  Infrastructure/Persistence/ClickHouse/ — ClickHouseFantasyStatsRepository, ClickHouseFantasyMetricAnalysisRepository
  UI/
    Http/                      — Controller/FantasyController (GET /api/fantasy/top-metrics), Response/TopFantasyMetricsResponse
    Console/                   — fantasy:top-metrics, fantasy:export-top-metrics
Statistics/                    — статистика игроков и форма команд
  Domain/
    PlayerStats/               — PlayerMetric
    TeamForm/                  — TeamFormAnalysis, TeamForm, Winrate
    Port/                      — PlayerStatsReadRepository, TeamFormAnalysisRepository
  Application/
    UseCase/, Service/         — GetTopPlayersByMetricUseCase/Service (whitelist метрик, пагинация),
                                 AnalyzeTeamsFormUseCase, TeamFormAnalyzer
    Dto/, Exception/           — GetTopPlayersQuery, TopPlayerByMetric, AnalyzeTeamsFormRequest, TeamFormResult;
                                 UnsupportedMetricException, InvalidPaginationException, InsufficientMatchesException
  Infrastructure/Persistence/ClickHouse/ — ClickHousePlayerStatsReadRepository, ClickHouseTeamFormAnalysisRepository
  UI/
    Http/                      — Controller/PlayerStatsController (GET /api/players/top), Response/TopPlayersResponse
    Console/                   — team:analyze-form
```

Цепочка вызова одинакова во всех контекстах: **UI** (парсинг входа → DTO запроса с `#[Assert]` → `ValidatorInterface`) → **UseCase** (примитивы → VO `Shared\Domain`) → **Service** (бизнес-валидация, оркестрация) → **порт** домена → реализация в Infrastructure. В Ingestion вместо Service стоит цепочка Messenger-хэндлеров.

## Правила зависимостей

Целевые правила — refactoring-plan.md → «Правила зависимостей». Автоматической проверки нет: тестов нет, deptrac не подключён. Проверка — grep по `use` (ddd-reviewer).

- `Shared\Domain` ни от чего не зависит. `Shared\UI\Http` → Symfony HttpKernel/HttpFoundation и `Shared\Domain`.
- `<Context>\Domain` → свой `Domain`, `Shared\Domain` и PHP. Без Symfony, Doctrine, ClickHouse, бандла dota-api. **Соблюдается.**
- `<Context>\Application` → свой `Domain`, `Shared`, `Psr\Log`, `Symfony\Component\Messenger`, `Symfony\Component\Validator\Constraints`. Не зависит от `Infrastructure` и `UI`. **Соблюдается.**
- `<Context>\Infrastructure` → свой `Domain`/`Application`, `Shared`, внешние библиотеки. Бандл dota-api — только в `Ingestion\Infrastructure\Source\*`. **Соблюдается.**
- `<Context>\UI` → свой `Application`, `Shared\UI\Http`. **Соблюдается.**
- Контексты не импортируют классы друг друга — связь только через `Shared` и через таблицы-read-модели владельца.
- Доменная логика (расчёты, правила отбора, квоты) живёт в `Domain`, а не в SQL-репозитории и не в контроллере.

**Отступления, которые остались после шага 2** — не образец, не тиражировать, закрываются шагами 3–5:

| Откуда | Что импортирует | Закрывается |
|---|---|---|
| `Fantasy\Application\Handler\ProcessFantasyScoreHandler` | `Ingestion\Application\Message\MatchFantasyCalculateMessage`, `MatchFailedMessage`; `Ingestion\Domain\Port\AnalyticMatchRepository`, `MatchProcessingRepository` | шаг 3 (событие `MatchImported`, свой порт чтения `MatchPerformanceReader`, статус — через `Shared\Event`) |
| `Fantasy\Domain\Scoring\FantasyScoreCalculator` | `Ingestion\Domain\Match\PlayerMatchStats` | шаг 3 (вход расчёта — модель Fantasy) |
| `Fantasy\Infrastructure\…\ClickHouseFantasyMetricAnalysisRepository` | `Ingestion\Domain\Port\MatchPlayersRepository` (ники игроков) | шаг 4 (`PlayerNameReader`) |
| `Statistics\Application\Service\TeamFormAnalyzer` | `Ingestion\Domain\Port\TeamMatchRepository`, `MatchRepository` | шаг 5 (`TeamMatchHistoryReader`) |
| `Shared\UI\Http\EventSubscriber\ExceptionSubscriber` | `Statistics\Application\Exception\UnsupportedMetricException`, `InvalidPaginationException` | шаг 5 (маркер-интерфейс в `Shared\Domain`) |

Прочие отступления MVP:

- `ClickHouseFantasyMetricAnalysisRepository` содержит логику анализа: вклад метрик, квоты эмблем через доменный `SelectTopMetricsByEmblemRules`, сборку связок (шаг 4)
- `Fantasy\Application\Service\GetTopFantasyMetricsService` — маппер домен → DTO, а не сервис; UseCase'ы Statistics и `FetchTeamMatchesUseCase` — проксирующие обёртки
- сообщения `async` несут `serialize(ImportedMatch)` (шаг 3 — только `match_id`)

## DI (`symfony/config/services.yaml`)

- `App\` — автовайринг и автоконфигурация всего `src/` (кроме `Kernel.php`). Явные определения сгруппированы по контекстам (`Shared`, `Ingestion`, `Fantasy`, `Statistics`)
- Порты-источники: `MatchSource` → `OpenDotaMatchSource`, `TeamMatchesSource` → `OpenDotaTeamMatchesSource` (выбор источника по сценарию). `Dota2ApiClient` регистрирует бандл, конфиг — `config/packages/dota2_api.yaml` (формат 2.0: `opendota.*`, `stratz.*`, `timeout: 30`, `max_retries: 3`)
- Интерфейсы ClickHouse-репозиториев алиасятся явно на реализации. Postgres-репозитории (`MatchRepository`, `MatchPlayersRepository`, `MatchProcessingRepository`) резолвятся автовайрингом по единственной реализации, `TeamMatchRepository` — явным алиасом
- `ClickHouseDB\Client` — через фабрику `Shared\Infrastructure\ClickHouse\ClickHouseFactory::create`. `ClickHouseTeamFormAnalysisRepository` получает саму фабрику и создаёт клиент на каждый вызов
- `FantasyScoringConfig` — 18 аргументов из `parameters.fantasy_scoring.*` (fantasy.md)
- `ExportTopFantasyMetricsCommand::$projectDir` = `%kernel.project_dir%`; `KafkaTopicsConfig` — имена топиков строками
- Роуты (`config/routes.yaml`) — атрибутные, по ресурсу на контекст: `src/{Ingestion,Fantasy,Statistics}/UI/Http/Controller/`


## Messenger и транспорты (`config/packages/messenger.yaml`)

| Транспорт | DSN | Сообщения | Ретраи |
|---|---|---|---|
| `kafka_parse_matches` | `ed+kafka://kafka:9092`, топик `dota2.matches.parse.v1`, group `matches-parser`, `earliest` | `ParseMatchMessage` | `max_retries: 0` |
| `async` | `doctrine://default?auto_setup=true` (таблица `messenger_messages` в Postgres) | `MatchFetchedMessage`, `MatchAnalyticsProcessedMessage`, `MatchFantasyCalculateMessage`, `MatchFailedMessage` | 3, задержка 1с ×2 |
| `kafka_failed_matches` | `ed+kafka://kafka:9092`, топик `dota2.matches.failed.v1`, `acks: all`, сериализатор `MatchFailedKafkaSerializer` | `MatchFailedKafkaMessage` | — (только продюсер, консьюмера нет) |

Общие настройки консьюмера Kafka — `config/packages/event_drive_kafka_transport.yaml` (`commit_async`, `enable.auto.commit: false`, `consume_timeout_ms: 500`).

Сообщения между хэндлерами несут `matchId` и `serializedMatch` — `serialize()` модели приложения `ImportedMatch` (геттер `getMatch()` делает `unserialize()`). Хэндлеры ловят `Throwable` сами и не пробрасывают исключение, поэтому ретраи `async` на практике не срабатывают. Ошибка оформляется явно: статус `failed` + `MatchFailedMessage`.

## Конвейер обработки матча

```
POST /api/matches/parse | parse:kafka:match | team:fetch-matches | match:retry-failed
  → ParseMatchMessage(matchId)                         → kafka_parse_matches
FetchMatchFromApiHandler
  match_processing: нет строки → INSERT pending; status=completed → пропуск (любой другой статус, в т.ч. failed, обрабатывается заново)
  markAsFetched (status=fetched, fetched_at) — ДО запроса к API
  MatchSource::fetch(MatchId) → ImportedMatch           (OpenDota: openDota()->matches()->getMatch; ошибка/пустой ответ → MatchSourceException)
  → MatchFetchedMessage(serialize(ImportedMatch))      → async
ProcessMatchAnalyticsHandler
  ImportedMatch::getPlayerStats() → 10 × PlayerMatchStats → INSERT в CH player_match_stats
  markAnalyticsProcessed (analytics_completed)
  → MatchAnalyticsProcessedMessage                     → async
PersistMatchDataHandler
  UPSERT matches (start_time, duration, radiant_win, region = id региона)
  INSERT match_players … ON CONFLICT DO NOTHING (player_pro_name)
  → MatchFantasyCalculateMessage                       → async
ProcessFantasyScoreHandler
  processing_fantasy → SELECT player_match_stats WHERE match_id (CH) → FantasyScoreCalculator × 10
  → INSERT в CH player_match_fantasy_stats → fantasy_completed → completed
  (нет строк статистики → warning и return, статус остаётся processing_fantasy)
Ошибка в любом хэндлере → status=failed → MatchFailedMessage(errorType) → async
MatchFailedHandler → MatchFailedKafkaMessage{match_id, error_type, error_message, timestamp} → kafka_failed_matches (JSON)
```

`errorType`: `invalid_match_id`, `fetch_error`, `analytics_error`, `persist_error`, `fantasy_calculation_error`.

Статусы `MatchProcessingStatus`: `pending`, `fetched`, `analytics_completed`, `processing_fantasy`, `fantasy_completed`, `completed`, `failed`. Значения `fetching` и `processing_analytics` объявлены, но не выставляются.

`match:retry-failed` берёт все строки `status = 'failed'` и шлёт по `ParseMatchMessage` на каждую: матч проходит весь конвейер заново, включая повторный INSERT в ClickHouse (known-issues.md → дубли).

## Поток Team

```
team:fetch-matches <id> [--limit] [--date-from] [--date-to]
  → FetchTeamMatchesService: TeamMatchesSource::listByTeam(TeamId) → TeamMatchRef[] (OpenDota /teams/{id}/matches)
    фильтр по дате → до limit матчей: dispatch ParseMatchMessage + TeamMatch(team, match, isWin)
    ошибка источника → TeamMatchesSourceException на всю команду (known-issues.md §14)
  → PsqlTeamMatchRepository::saveBulk (UPSERT team_matches в транзакции)

team:analyze-form "<ids>" [--date-from] [--date-to]     (OpenDota не вызывается)
  → TeamFormAnalyzer: team_matches INNER JOIN matches (только обработанные матчи), < 5 → InsufficientMatchesException
    winrate = wins/total ×100 → TeamForm (≥70 prime, ≥60 high, ≥40 medium, иначе low)
    регион — самый частый matches.region (по запросу на матч), last_match_time — первый по start_time DESC
    team_name = "Team {id}" (имя не запрашивается)
  → INSERT в CH team_form_analysis
```

Чтение `team_form_analysis` (`findByTeamId`, `findAll`, `findFavorites`, `findUnderdogs`) реализовано в репозитории, но ни одним эндпоинтом или командой не используется.

## Инфраструктура (`docker-compose.yml`)

| Сервис | Контейнер | Порт хоста | Назначение |
|---|---|---|---|
| `app` | `dota_app` | — | php-fpm 8.3 (`docker/php/Dockerfile`: librdkafka 2.10 из исходников, `rdkafka`, `pdo_pgsql`, `pcntl`), `./symfony` → `/var/www` |
| `nginx` | `dota_nginx` | 8080 | `public/index.php` → `app:9000` |
| `postgres` | `dota_postgres` | 5432 | БД `dota`, пользователь/пароль `dota/dota` |
| `clickhouse` | `dota_clickhouse` | 8123, 9000 | пользователь `default` без пароля (`docker/clickhouse/users.xml`) |
| `kafka` | `dota_kafka` | 9092 | Apache Kafka 4.1.1 KRaft, один брокер |
| `kafka-ui` | `kafka-ui` | 8081 | Provectus Kafka UI |
| `worker_kafka` | `dota_worker_kafka` | — | `messenger:consume kafka_parse_matches --time-limit=3600` |
| `worker_async` | `dota_worker_async` | — | `messenger:consume async --time-limit=3600` |

Redis поднимается и прописан в `.env` (`REDIS_URL`), но приложением не используется (кеш — файловый по умолчанию). Консоль: `docker exec -it dota_app php bin/console …`. Таблицы БД создаются вручную (database.md).
